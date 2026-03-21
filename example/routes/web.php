<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use OmarSaiouf\ProcessManager\Contracts\ProcessManagerInterface;

$manager = function (string $driver): ProcessManagerInterface {
    config()->set('process-manager.default', $driver);
    app()->forgetInstance(ProcessManagerInterface::class);

    return app(ProcessManagerInterface::class);
};

$response = function (callable $callback): JsonResponse {
    try {
        return response()->json([
            'ok' => true,
            'data' => $callback(),
        ]);
    } catch (Throwable $exception) {
        return response()->json([
            'ok' => false,
            'error' => $exception->getMessage(),
        ], 500);
    }
};

Route::get('/', function () {
    return response()->json([
        'package' => 'omarsaiouf/screen-marager',
        'active_driver_from_env' => config('process-manager.default'),
        'examples' => [
            'screen' => [
                'overview' => '/examples/screen',
                'create' => '/examples/screen/create/demo-screen',
                'all' => '/examples/screen/all',
                'capture' => '/examples/screen/capture/demo-screen',
                'attach' => '/examples/screen/attach/demo-screen',
                'stop' => '/examples/screen/stop/demo-screen',
            ],
            'supervisor' => [
                'overview' => '/examples/supervisor',
                'create' => '/examples/supervisor/create/demo-supervisor',
                'all' => '/examples/supervisor/all',
                'capture' => '/examples/supervisor/capture/demo-supervisor',
                'stop' => '/examples/supervisor/stop/demo-supervisor',
            ],
            'systemd' => [
                'overview' => '/examples/systemd',
                'create' => '/examples/systemd/create/demo-systemd',
                'all' => '/examples/systemd/all',
                'capture' => '/examples/systemd/capture/demo-systemd',
                'stop' => '/examples/systemd/stop/demo-systemd',
            ],
        ],
    ]);
});

Route::prefix('examples/screen')->group(function () use ($manager, $response) {
    Route::get('/', function () use ($response) {
        return $response(fn () => [
            'driver' => 'screen',
            'description' => 'Interactive detached sessions using GNU screen.',
            'sample_command' => 'while true; do echo "screen alive $(date)"; sleep 5; done',
            'supports' => ['create', 'all', 'exists', 'sendCommand', 'captureOutput', 'attachCommand', 'stop', 'restart'],
        ]);
    });

    Route::get('/create/{sessionName}', function (string $sessionName) use ($manager, $response) {
        return $response(function () use ($manager, $sessionName) {
            $processManager = $manager('screen');
            $command = 'printf "screen booted\n"; exec bash';

            return [
                'driver' => 'screen',
                'created' => $processManager->create($sessionName, $command, base_path()),
                'session' => $sessionName,
                'command' => $command,
            ];
        });
    });

    Route::get('/all', function () use ($manager, $response) {
        return $response(fn () => [
            'driver' => 'screen',
            'sessions' => $manager('screen')->all(),
        ]);
    });

    Route::get('/capture/{sessionName}', function (string $sessionName) use ($manager, $response) {
        return $response(fn () => [
            'driver' => 'screen',
            'session' => $sessionName,
            'output' => $manager('screen')->captureOutput($sessionName),
        ]);
    });

    Route::get('/attach/{sessionName}', function (string $sessionName) use ($manager, $response) {
        return $response(fn () => [
            'driver' => 'screen',
            'session' => $sessionName,
            'attach_command' => $manager('screen')->attachCommand($sessionName),
        ]);
    });

    Route::get('/stop/{sessionName}', function (string $sessionName) use ($manager, $response) {
        return $response(fn () => [
            'driver' => 'screen',
            'session' => $sessionName,
            'stopped' => $manager('screen')->stop($sessionName),
        ]);
    });
});

Route::prefix('examples/supervisor')->group(function () use ($manager, $response) {
    Route::get('/', function () use ($response) {
        return $response(fn () => [
            'driver' => 'supervisor',
            'description' => 'Managed background programs through supervisorctl.',
            'sample_command' => 'php artisan queue:work --sleep=1 --tries=1',
            'supports' => ['create', 'all', 'exists', 'captureOutput', 'stop', 'restart'],
            'notes' => [
                'sendCommand is not supported because supervisor is not interactive.',
                'attachCommand is not supported because supervisor does not attach to running processes.',
                'PROCESS_MANAGER_SUPERVISOR_CONFIG_PATH should point to a real supervisor include path such as /etc/supervisor/conf.d.',
                'Set PROCESS_MANAGER_SUPERVISOR_USE_SUDO=true when the web user cannot access supervisorctl or write to the supervisor config path.',
            ],
        ]);
    });

    Route::get('/create/{sessionName}', function (string $sessionName) use ($manager, $response) {
        return $response(function () use ($manager, $sessionName) {
            $processManager = $manager('supervisor');
            $command = 'php artisan queue:work --sleep=1 --tries=1';

            return [
                'driver' => 'supervisor',
                'created' => $processManager->create($sessionName, $command, base_path()),
                'program' => $sessionName,
                'command' => $command,
                'config_path' => config('process-manager.drivers.supervisor.config_path'),
                'log_path' => config('process-manager.drivers.supervisor.log_path'),
                'use_sudo' => config('process-manager.drivers.supervisor.use_sudo'),
            ];
        });
    });

    Route::get('/all', function () use ($manager, $response) {
        return $response(fn () => [
            'driver' => 'supervisor',
            'programs' => $manager('supervisor')->all(),
        ]);
    });

    Route::get('/capture/{sessionName}', function (string $sessionName) use ($manager, $response) {
        return $response(fn () => [
            'driver' => 'supervisor',
            'program' => $sessionName,
            'output' => $manager('supervisor')->captureOutput($sessionName),
        ]);
    });

    Route::get('/stop/{sessionName}', function (string $sessionName) use ($manager, $response) {
        return $response(fn () => [
            'driver' => 'supervisor',
            'program' => $sessionName,
            'stopped' => $manager('supervisor')->stop($sessionName),
        ]);
    });
});

Route::prefix('examples/systemd')->group(function () use ($manager, $response) {
    Route::get('/', function () use ($response) {
        return $response(fn () => [
            'driver' => 'systemd',
            'description' => 'Managed Linux services through systemctl and journalctl.',
            'sample_command' => 'php artisan schedule:work',
            'supports' => ['create', 'all', 'exists', 'captureOutput', 'stop', 'restart'],
            'notes' => [
                'sendCommand is not supported because systemd services are not interactive.',
                'attachCommand is not supported because systemd does not provide a session attach model.',
                'PROCESS_MANAGER_SYSTEMD_UNIT_PATH should point to a real unit path such as /etc/systemd/system or ~/.config/systemd/user.',
                'Set PROCESS_MANAGER_SYSTEMD_USE_SUDO=true when the web user cannot manage system services directly.',
            ],
        ]);
    });

    Route::get('/create/{sessionName}', function (string $sessionName) use ($manager, $response) {
        return $response(function () use ($manager, $sessionName) {
            $processManager = $manager('systemd');
            $command = 'php artisan schedule:work';

            return [
                'driver' => 'systemd',
                'created' => $processManager->create($sessionName, $command, base_path()),
                'service' => $sessionName,
                'command' => $command,
                'unit_path' => config('process-manager.drivers.systemd.unit_path'),
                'scope' => config('process-manager.drivers.systemd.scope'),
                'use_sudo' => config('process-manager.drivers.systemd.use_sudo'),
            ];
        });
    });

    Route::get('/all', function () use ($manager, $response) {
        return $response(fn () => [
            'driver' => 'systemd',
            'services' => $manager('systemd')->all(),
        ]);
    });

    Route::get('/capture/{sessionName}', function (string $sessionName) use ($manager, $response) {
        return $response(fn () => [
            'driver' => 'systemd',
            'service' => $sessionName,
            'output' => $manager('systemd')->captureOutput($sessionName),
        ]);
    });

    Route::get('/stop/{sessionName}', function (string $sessionName) use ($manager, $response) {
        return $response(fn () => [
            'driver' => 'systemd',
            'service' => $sessionName,
            'stopped' => $manager('systemd')->stop($sessionName),
        ]);
    });
});
