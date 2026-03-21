<?php

namespace OmarSaiouf\ProcessManager\Drivers;

use OmarSaiouf\ProcessManager\Contracts\ProcessManagerInterface;
use OmarSaiouf\ProcessManager\DTOs\ScreenSession;

class SystemdDriver extends AbstractShellDriver implements ProcessManagerInterface
{
    public function __construct(
        private readonly array $config = [],
    ) {}

    protected function requiredBinary(): string
    {
        return $this->config['binary'] ?? 'systemctl';
    }

    public function create(string $sessionName, string $command, ?string $workingDir = null): bool
    {
        $this->ensureNotExists($sessionName);
        $this->ensureDirectoryExists($this->unitPathDirectory());

        $this->writeFile(
            $this->unitFilePath($sessionName),
            $this->buildUnitFile($sessionName, $command, $workingDir)
        );

        $this->run($this->systemctl('daemon-reload'));

        if ($this->config['enable_on_create'] ?? false) {
            $this->run($this->systemctl('enable ' . escapeshellarg($this->unitName($sessionName))));
        }

        return $this->run($this->systemctl('start ' . escapeshellarg($this->unitName($sessionName))));
    }

    public function all(): array
    {
        $sessions = [];

        foreach ($this->managedUnitFiles() as $unitFile) {
            $unitName = basename($unitFile);
            $serviceName = substr($unitName, 0, -8);
            $status = trim($this->runAndGet(
                $this->systemctl('show ' . escapeshellarg($unitName) . ' --property=ActiveState --value'),
                allowEmptyResult: true
            ));

            $sessions[] = new ScreenSession(
                id: $unitName,
                name: $serviceName,
                status: $status === '' ? 'inactive' : $status,
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
        $this->unsupportedOperation('sendCommand', 'systemd');
    }

    public function stop(string $sessionName): bool
    {
        $session = $this->findSession($sessionName);

        if ($session === null) {
            return false;
        }

        return $this->run($this->systemctl('stop ' . escapeshellarg($session->id)));
    }

    public function restart(string $sessionName, string $command, ?string $workingDir = null): bool
    {
        $this->ensureDirectoryExists($this->unitPathDirectory());

        $this->writeFile(
            $this->unitFilePath($sessionName),
            $this->buildUnitFile($sessionName, $command, $workingDir)
        );

        $this->run($this->systemctl('daemon-reload'));

        return $this->run($this->systemctl('restart ' . escapeshellarg($this->unitName($sessionName))));
    }

    public function captureOutput(string $sessionName, bool $includeScrollback = true): string
    {
        $session = $this->findSession($sessionName);

        if ($session === null) {
            throw new \RuntimeException("Systemd service [$sessionName] not found.");
        }

        $this->ensureCommandIsInstalled('journalctl');

        $limit = (int) ($this->config['tail_lines'] ?? 200);
        $suffix = $includeScrollback ? '' : ' -n ' . $limit;

        return trim($this->runAndGet(
            $this->withSudo('journalctl -u ' . escapeshellarg($session->id) . $suffix . ' --no-pager'),
            allowEmptyResult: true
        ));
    }

    public function attachCommand(string $sessionName): string
    {
        $this->unsupportedOperation('attachCommand', 'systemd');
    }

    private function buildUnitFile(string $sessionName, string $command, ?string $workingDir = null): string
    {
        $directory = $workingDir ?? ($this->config['working_directory'] ?? base_path());
        $restartPolicy = $this->config['restart'] ?? 'always';
        $userLine = isset($this->config['user']) && $this->config['user'] !== ''
            ? 'User=' . $this->config['user'] . PHP_EOL
            : '';

        return implode(PHP_EOL, [
            '[Unit]',
            'Description=Process Manager - ' . $sessionName,
            'After=network.target',
            '',
            '[Service]',
            'Type=simple',
            'WorkingDirectory=' . $directory,
            'ExecStart=/bin/bash -lc ' . escapeshellarg($command),
            'Restart=' . $restartPolicy,
            rtrim($userLine),
            '',
            '[Install]',
            'WantedBy=multi-user.target',
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

    private function managedUnitFiles(): array
    {
        $path = $this->unitPathDirectory();

        if (!is_dir($path)) {
            return [];
        }

        return glob($path . '/*.service') ?: [];
    }

    private function systemctl(string $command): string
    {
        $binary = $this->config['binary'] ?? 'systemctl';
        $scope = $this->config['scope'] ?? 'system';

        if ($scope === 'user') {
            return $this->withSudo(trim($binary . ' --user ' . $command));
        }

        return $this->withSudo(trim($binary . ' ' . $command));
    }

    private function unitPathDirectory(): string
    {
        if (!empty($this->config['unit_path'])) {
            return rtrim($this->config['unit_path'], '/');
        }

        if (($this->config['scope'] ?? 'system') === 'user') {
            $home = rtrim($_SERVER['HOME'] ?? getenv('HOME') ?: '', '/');

            return $home . '/.config/systemd/user';
        }

        return '/etc/systemd/system';
    }

    private function unitFilePath(string $sessionName): string
    {
        return $this->unitPathDirectory() . '/' . $this->unitName($sessionName);
    }

    private function unitName(string $sessionName): string
    {
        return str_ends_with($sessionName, '.service') ? $sessionName : $sessionName . '.service';
    }

    private function ensureNotExists(string $sessionName): void
    {
        if ($this->exists($sessionName)) {
            throw new \RuntimeException("Systemd service [$sessionName] already exists.");
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

    private function ensureCommandIsInstalled(string $binary): void
    {
        $process = \Illuminate\Support\Facades\Process::run('command -v ' . escapeshellarg($binary));

        if (!$process->successful()) {
            throw new \RuntimeException("The \"$binary\" binary is not installed or not available in PATH.");
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
            throw new \RuntimeException("Unable to write systemd unit file [$path].");
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
