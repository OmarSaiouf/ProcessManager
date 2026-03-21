<?php 

namespace OmarSaiouf\ProcessManager\Contracts;

interface ProcessManagerInterface
{
    /**
     * Create a new detached screen session and run the given command inside it.
     *
     * Example:
     * $manager->create('queue-worker', 'php artisan queue:work', base_path());
     */
    public function create(string $sessionName, string $command, ?string $workingDir = null): bool;

    /**
     * Return all active screen sessions.
     *
     * Example:
     * $sessions = $manager->all();
     */
    public function all(): array;

    /**
     * Check whether a screen session exists by id, name, or full session key.
     *
     * Example:
     * $manager->exists('12345');
     * $manager->exists('queue-worker');
     */
    public function exists(string $sessionName): bool;

    /**
     * Send a command to an existing screen session.
     *
     * Example:
     * $manager->sendCommand('queue-worker', 'php artisan cache:clear');
     */
    public function sendCommand(string $sessionName, string $command): bool;

    /**
     * Stop an existing screen session.
     *
     * Example:
     * $manager->stop('queue-worker');
     */
    public function stop(string $sessionName): bool;

    /**
     * Restart a screen session by stopping it and creating it again.
     *
     * Example:
     * $manager->restart('queue-worker', 'php artisan queue:work', base_path());
     */
    public function restart(string $sessionName, string $command, ?string $workingDir = null): bool;

    /**
     * Capture the visible output of a screen session, with optional scrollback.
     *
     * Example:
     * $output = $manager->captureOutput('queue-worker');
     */
    public function captureOutput(string $sessionName, bool $includeScrollback = true): string;

    /**
     * Return the terminal command needed to attach to a screen session.
     *
     * Example:
     * $command = $manager->attachCommand('queue-worker');
     */
    public function attachCommand(string $sessionName): string;
}
