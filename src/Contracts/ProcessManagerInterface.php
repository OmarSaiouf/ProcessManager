<?php 

namespace OmarSaiouf\ProcessManager\Contracts;

interface ProcessManagerInterface
{
    /**
     * Create a new managed process session and run the given command inside it.
     *
     * Example:
     * $manager->create('queue-worker', 'php artisan queue:work', base_path());
     */
    public function create(string $sessionName, string $command, ?string $workingDir = null): bool;

    /**
     * Return all managed process sessions for the active driver.
     *
     * Example:
     * $sessions = $manager->all();
     */
    public function all(): array;

    /**
     * Check whether a managed session exists by id, name, or driver-specific key.
     *
     * Example:
     * $manager->exists('12345');
     * $manager->exists('queue-worker');
     */
    public function exists(string $sessionName): bool;

    /**
     * Send a command to an existing managed session when the driver supports interactive input.
     *
     * Example:
     * $manager->sendCommand('queue-worker', 'php artisan cache:clear');
     */
    public function sendCommand(string $sessionName, string $command): bool;

    /**
     * Stop an existing managed session.
     *
     * Example:
     * $manager->stop('queue-worker');
     */
    public function stop(string $sessionName): bool;

    /**
     * Restart a managed session by stopping it and creating it again.
     *
     * Example:
     * $manager->restart('queue-worker', 'php artisan queue:work', base_path());
     */
    public function restart(string $sessionName, string $command, ?string $workingDir = null): bool;

    /**
     * Capture the current output or logs of a managed session.
     *
     * Example:
     * $output = $manager->captureOutput('queue-worker');
     */
    public function captureOutput(string $sessionName, bool $includeScrollback = true): string;

    /**
     * Return the terminal command needed to attach to a managed session when supported.
     *
     * Example:
     * $command = $manager->attachCommand('queue-worker');
     */
    public function attachCommand(string $sessionName): string;
}
