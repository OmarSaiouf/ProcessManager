<?php

namespace OmarSaiouf\ProcessManager\Drivers;

use OmarSaiouf\ProcessManager\Contracts\ProcessManagerInterface;
use OmarSaiouf\ProcessManager\DTOs\ScreenSession;

class SupervisorDriver extends AbstractShellDriver implements ProcessManagerInterface
{
    public function __construct(
        private readonly array $config = [],
    ) {}

    protected function requiredBinary(): string
    {
        return $this->config['binary'] ?? 'supervisorctl';
    }

    public function create(string $sessionName, string $command, ?string $workingDir = null): bool
    {
        $this->ensureNotExists($sessionName);

        $this->ensureDirectoryExists($this->configPathDirectory());
        $this->ensureDirectoryExists($this->logPathDirectory());

        $this->writeFile(
            $this->programConfigPath($sessionName),
            $this->buildProgramConfig($sessionName, $command, $workingDir)
        );

        $this->run($this->supervisorctl('reread'));
        $this->run($this->supervisorctl('update'));

        return $this->run($this->supervisorctl('start ' . escapeshellarg($sessionName)));
    }

    public function all(): array
    {
        $output = $this->runAndGet($this->supervisorctl('status'), allowEmptyResult: true);
        $sessions = [];

        foreach (preg_split('/\r?\n/', trim($output)) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            [$name, $status] = preg_split('/\s+/', $line, 3);

            $sessions[] = new ScreenSession(
                id: $name,
                name: $name,
                status: $status,
            );
        }

        return $sessions;
    }

    public function exists(string $sessionName): bool
    {
        return $this->findSession($sessionName) !== null;
    }

    public function sendCommand(string $sessionName, string $command): bool
    {
        $this->unsupportedOperation('sendCommand', 'supervisor');
    }

    public function stop(string $sessionName): bool
    {
        $session = $this->findSession($sessionName);

        if ($session === null) {
            return false;
        }

        return $this->run($this->supervisorctl('stop ' . escapeshellarg($session->name)));
    }

    public function restart(string $sessionName, string $command, ?string $workingDir = null): bool
    {
        $this->upsertProgramConfig($sessionName, $command, $workingDir);
        $this->run($this->supervisorctl('reread'));
        $this->run($this->supervisorctl('update'));

        return $this->run($this->supervisorctl('restart ' . escapeshellarg($sessionName)));
    }

    public function captureOutput(string $sessionName, bool $includeScrollback = true): string
    {
        $session = $this->findSession($sessionName);

        if ($session === null) {
            throw new \RuntimeException("Supervisor program [$sessionName] not found.");
        }

        $logPath = $this->logFilePath($session->name);

        if (!is_file($logPath)) {
            return '';
        }

        $contents = file_get_contents($logPath);

        if ($contents === false) {
            throw new \RuntimeException("Unable to read supervisor log output for [$sessionName].");
        }

        if ($includeScrollback) {
            return rtrim($contents);
        }

        $lines = preg_split('/\r?\n/', trim($contents));
        $tail = array_slice($lines, -((int) ($this->config['tail_lines'] ?? 200)));

        return implode(PHP_EOL, $tail);
    }

    public function attachCommand(string $sessionName): string
    {
        $this->unsupportedOperation('attachCommand', 'supervisor');
    }

    private function upsertProgramConfig(string $sessionName, string $command, ?string $workingDir = null): void
    {
        $this->ensureDirectoryExists($this->configPathDirectory());
        $this->ensureDirectoryExists($this->logPathDirectory());

        $this->writeFile(
            $this->programConfigPath($sessionName),
            $this->buildProgramConfig($sessionName, $command, $workingDir)
        );
    }

    private function buildProgramConfig(string $sessionName, string $command, ?string $workingDir = null): string
    {
        $directory = $workingDir ?? ($this->config['working_directory'] ?? base_path());
        $autostart = ($this->config['autostart'] ?? true) ? 'true' : 'false';
        $autorestart = ($this->config['autorestart'] ?? true) ? 'true' : 'false';
        $startSecs = (int) ($this->config['startsecs'] ?? 1);
        $userLine = isset($this->config['user']) && $this->config['user'] !== ''
            ? 'user=' . $this->config['user'] . PHP_EOL
            : '';

        return implode(PHP_EOL, [
            sprintf('[program:%s]', $sessionName),
            'command=' . $command,
            'directory=' . $directory,
            'autostart=' . $autostart,
            'autorestart=' . $autorestart,
            'startsecs=' . $startSecs,
            'stdout_logfile=' . $this->logFilePath($sessionName),
            'stderr_logfile=' . $this->logFilePath($sessionName),
            'redirect_stderr=true',
            rtrim($userLine),
            '',
        ]);
    }

    private function findSession(string $identifier): ?ScreenSession
    {
        foreach ($this->all() as $session) {
            if ($session->id === $identifier || $session->name === $identifier) {
                return $session;
            }
        }

        return null;
    }

    private function supervisorctl(string $command): string
    {
        $binary = $this->config['binary'] ?? 'supervisorctl';
        $serverUrl = $this->config['server_url'] ?? null;
        $prefix = $binary;

        if ($serverUrl) {
            $prefix .= ' -s ' . escapeshellarg($serverUrl);
        }

        return $this->withSudo(trim($prefix . ' ' . $command));
    }

    private function configPathDirectory(): string
    {
        return rtrim($this->config['config_path'] ?? '/etc/supervisor/conf.d', '/');
    }

    private function logPathDirectory(): string
    {
        return rtrim($this->config['log_path'] ?? storage_path('logs/process-manager/supervisor'), '/');
    }

    private function logFilePath(string $sessionName): string
    {
        return $this->logPathDirectory() . '/' . $sessionName . '.log';
    }

    private function programConfigPath(string $sessionName): string
    {
        return $this->configPathDirectory() . '/' . $sessionName . '.conf';
    }

    private function ensureNotExists(string $sessionName): void
    {
        if ($this->exists($sessionName)) {
            throw new \RuntimeException("Supervisor program [$sessionName] already exists.");
        }
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if ($this->shouldUseSudo()) {
            $this->run($this->withSudo('mkdir -p ' . escapeshellarg($path)));
            return;
        }

        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new \RuntimeException("Unable to create directory [$path].");
        }
    }

    private function writeFile(string $path, string $contents): void
    {
        if ($this->shouldUseSudo()) {
            $command = sprintf(
                'sh -c %s',
                escapeshellarg('printf %s ' . escapeshellarg($contents) . ' | ' . $this->sudoPrefix() . ' tee ' . escapeshellarg($path) . ' > /dev/null')
            );

            $this->run($command);
            return;
        }

        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException("Unable to write supervisor config [$path].");
        }
    }

    private function withSudo(string $command): string
    {
        if (!$this->shouldUseSudo()) {
            return $command;
        }

        return $this->sudoPrefix() . ' ' . $command;
    }

    private function sudoPrefix(): string
    {
        $binary = $this->config['sudo_binary'] ?? 'sudo';
        $nonInteractive = $this->config['sudo_non_interactive'] ?? true;

        return trim($binary . ($nonInteractive ? ' -n' : ''));
    }

    private function shouldUseSudo(): bool
    {
        return (bool) ($this->config['use_sudo'] ?? false);
    }
}
