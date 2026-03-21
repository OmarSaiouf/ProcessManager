<?php

namespace OmarSaiouf\ProcessManager;

use OmarSaiouf\ProcessManager\Contracts\ProcessManagerInterface;
use OmarSaiouf\ProcessManager\Drivers\ScreenDriver;
use OmarSaiouf\ProcessManager\Drivers\SupervisorDriver;
use OmarSaiouf\ProcessManager\Drivers\SystemdDriver;
use RuntimeException;

class ProcessManagerServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/process-manager.php', 'process-manager');

        $this->app->singleton(ProcessManagerInterface::class, function ($app) {
            $defaultDriver = $app['config']->get('process-manager.default', 'screen');
            $drivers = $app['config']->get('process-manager.drivers', []);

            return match ($defaultDriver) {
                'screen' => new ScreenDriver(),
                'supervisor' => new SupervisorDriver($drivers['supervisor'] ?? []),
                'systemd' => new SystemdDriver($drivers['systemd'] ?? []),
                default => throw new RuntimeException("Unsupported process manager driver [$defaultDriver]."),
            };
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/process-manager.php' => config_path('process-manager.php'),
        ], 'process-manager-config');
    }
}
