<?php

namespace OmarSaiouf\ProcessManager;

class ProcessManagerServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register()
    {
        $this->app->singleton(\OmarSaiouf\ProcessManager\Contracts\ProcessManagerInterface::class, function ($app) {
            return new \OmarSaiouf\ProcessManager\Drivers\ScreenDriver();
        });
    }
}