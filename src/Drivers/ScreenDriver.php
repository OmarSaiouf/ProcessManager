<?php 

namespace OmarSaiouf\ProcessManager\Drivers;

use OmarSaiouf\ProcessManager\Contracts\ProcessManagerInterface;
use OmarSaiouf\ProcessManager\DTOs\ScreenSession;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class ScreenDriver implements ProcessManagerInterface
{
    public function create(string $sessionName, string $command, ?string $workingDir = null): bool
    {
        $this->ensureNotExists($sessionName);

        $cmd = sprintf(
            "screen -d -m -S %s bash -c %s",
            escapeshellarg($sessionName),
            escapeshellarg($command)
        );

        return $this->run($cmd, $workingDir);
    }

    public function all(): array
    {
        $output = $this->runAndGet("screen -ls", allowEmptyResult: true);

        return $this->parseSessions($output);
    }

    public function exists(string $sessionName): bool
    {
        return $this->findSession($sessionName) !== null;
    }

    public function sendCommand(string $sessionName, string $command): bool
    {
        $sessionKey = $this->resolveSessionKey($sessionName);

        $cmd = sprintf(
            "screen -S %s -p 0 -X stuff %s",
            escapeshellarg($sessionKey),
            escapeshellarg($command . "\n")
        );

        return $this->run($cmd);
    }

    public function stop(string $sessionName): bool
    {
        $session = $this->findSession($sessionName);

        if ($session === null) {
            return false;
        }

        return $this->run("screen -S " . escapeshellarg($this->sessionKey($session)) . " -X quit");
    }

    public function restart(string $sessionName, string $command, ?string $workingDir = null): bool
    {
        $this->stop($sessionName);
        return $this->create($sessionName, $command, $workingDir);
    }

    public function captureOutput(string $sessionName, bool $includeScrollback = true): string
    {
        $sessionKey = $this->resolveSessionKey($sessionName);

        $tempFile = tempnam(sys_get_temp_dir(), 'screen-output-');

        if ($tempFile === false) {
            throw new RuntimeException('Unable to create a temporary file for capturing screen output.');
        }

        try {
            $cmd = sprintf(
                'screen -S %s -X hardcopy %s %s',
                escapeshellarg($sessionKey),
                $includeScrollback ? '-h' : '',
                escapeshellarg($tempFile)
            );

            $this->run(trim($cmd));

            $contents = file_get_contents($tempFile);

            if ($contents === false) {
                throw new RuntimeException('Unable to read the captured screen output.');
            }

            return rtrim($contents);
        } finally {
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    public function attachCommand(string $sessionName): string
    {
        $sessionKey = $this->resolveSessionKey($sessionName);

        return 'screen -r ' . escapeshellarg($sessionKey);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function run(string $command, ?string $path = null): bool
    {
        $this->ensureScreenIsInstalled();

        $process = Process::path($path ?? base_path())->run($command);

        if (!$process->successful()) {
            throw new RuntimeException($this->resolveErrorMessage($process->errorOutput(), $process->output()));
        }

        return true;
    }

    private function runAndGet(string $command, bool $allowEmptyResult = false): string
    {
        $this->ensureScreenIsInstalled();

        $process = Process::run($command);

        if (!$process->successful()) {
            $combinedOutput = trim($process->output() . "\n" . $process->errorOutput());

            if ($allowEmptyResult && $this->isNoSocketsOutput($combinedOutput)) {
                return '';
            }

            throw new RuntimeException($this->resolveErrorMessage($process->errorOutput(), $process->output()));
        }

        return $process->output();
    }

    private function ensureExists(string $name): void
    {
        if ($this->findSession($name) === null) {
            throw new RuntimeException("Screen session [$name] not found.");
        }
    }

    private function ensureNotExists(string $name): void
    {
        if ($this->findSession($name) !== null) {
            throw new RuntimeException("Screen session [$name] already exists.");
        }
    }

    private function findSession(string $identifier): ?ScreenSession
    {
        foreach ($this->all() as $session) {
            if (
                $session->id === $identifier
                || $session->name === $identifier
                || $this->sessionKey($session) === $identifier
            ) {
                return $session;
            }
        }

        return null;
    }

    private function resolveSessionKey(string $identifier): string
    {
        $session = $this->findSession($identifier);

        if ($session === null) {
            throw new RuntimeException("Screen session [$identifier] not found.");
        }

        return $this->sessionKey($session);
    }

    private function sessionKey(ScreenSession $session): string
    {
        return $session->id . '.' . $session->name;
    }

    private function parseSessions(string $output): array
    {
        $sessions = [];

        foreach (explode("\n", $output) as $line) {
            if (
                preg_match(
                    '/^\s*(\d+\.\S+)\s+(?:\([^)]+\)\s+)?\((Detached|Attached)\)\s*$/',
                    trim($line),
                    $matches
                )
            ) {
                [$id, $name] = explode('.', $matches[1], 2);

                $sessions[] = new ScreenSession(
                    id: $id,
                    name: $name,
                    status: $matches[2],
                );
            }
        }

        return $sessions;
    }

    private function ensureScreenIsInstalled(): void
    {
        static $checked = false;

        if ($checked) {
            return;
        }

        $process = Process::run('command -v screen');

        if (!$process->successful()) {
            throw new RuntimeException('The "screen" binary is not installed or not available in PATH.');
        }

        $checked = true;
    }

    private function isNoSocketsOutput(string $output): bool
    {
        return str_contains($output, 'No Sockets found');
    }

    private function resolveErrorMessage(string $errorOutput, string $output = ''): string
    {
        $message = trim($errorOutput);

        if ($message !== '') {
            return $message;
        }

        $message = trim($output);

        return $message !== '' ? $message : 'The process failed without returning an error message.';
    }
}
