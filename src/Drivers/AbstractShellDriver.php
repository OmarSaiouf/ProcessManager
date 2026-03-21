<?php

namespace OmarSaiouf\ProcessManager\Drivers;

use Illuminate\Support\Facades\Process;
use RuntimeException;

abstract class AbstractShellDriver
{
    abstract protected function requiredBinary(): string;

    protected function run(string $command, ?string $path = null): bool
    {
        $this->ensureBinaryIsInstalled();

        $process = Process::path($path ?? base_path())->run($command);

        if (!$process->successful()) {
            throw new RuntimeException($this->resolveErrorMessage($process->errorOutput(), $process->output()));
        }

        return true;
    }

    protected function runAndGet(string $command, bool $allowEmptyResult = false): string
    {
        $this->ensureBinaryIsInstalled();

        $process = Process::run($command);

        if (!$process->successful()) {
            $combinedOutput = trim($process->output() . "\n" . $process->errorOutput());

            if ($allowEmptyResult && $this->isEmptyResultOutput($combinedOutput)) {
                return '';
            }

            throw new RuntimeException($this->resolveErrorMessage($process->errorOutput(), $process->output()));
        }

        return $process->output();
    }

    protected function ensureBinaryIsInstalled(): void
    {
        static $checked = [];

        $binary = $this->requiredBinary();

        if ($checked[$binary] ?? false) {
            return;
        }

        $process = Process::run('command -v ' . escapeshellarg($binary));

        if (!$process->successful()) {
            throw new RuntimeException(sprintf(
                'The "%s" binary is not installed or not available in PATH.',
                $binary
            ));
        }

        $checked[$binary] = true;
    }

    protected function isEmptyResultOutput(string $output): bool
    {
        return trim($output) === '';
    }

    protected function resolveErrorMessage(string $errorOutput, string $output = ''): string
    {
        $message = trim($errorOutput);

        if ($message !== '') {
            return $message;
        }

        $message = trim($output);

        return $message !== '' ? $message : 'The process failed without returning an error message.';
    }

    protected function unsupportedOperation(string $operation, string $driver): never
    {
        throw new RuntimeException(sprintf(
            'The "%s" operation is not supported by the "%s" driver.',
            $operation,
            $driver
        ));
    }
}
