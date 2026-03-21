<?php

return [
    'default' => env('PROCESS_MANAGER_DRIVER', 'screen'),

    'drivers' => [
        'screen' => [],

        'supervisor' => [
            'binary' => env('PROCESS_MANAGER_SUPERVISOR_BINARY', 'supervisorctl'),
            'use_sudo' => env('PROCESS_MANAGER_SUPERVISOR_USE_SUDO', false),
            'sudo_binary' => env('PROCESS_MANAGER_SUPERVISOR_SUDO_BINARY', 'sudo'),
            'sudo_non_interactive' => env('PROCESS_MANAGER_SUPERVISOR_SUDO_NON_INTERACTIVE', true),
            'server_url' => env('PROCESS_MANAGER_SUPERVISOR_SERVER_URL'),
            'config_path' => env('PROCESS_MANAGER_SUPERVISOR_CONFIG_PATH'),
            'log_path' => env('PROCESS_MANAGER_SUPERVISOR_LOG_PATH', storage_path('logs/process-manager/supervisor')),
            'working_directory' => env('PROCESS_MANAGER_SUPERVISOR_WORKING_DIRECTORY'),
            'autostart' => env('PROCESS_MANAGER_SUPERVISOR_AUTOSTART', true),
            'autorestart' => env('PROCESS_MANAGER_SUPERVISOR_AUTORESTART', true),
            'startsecs' => env('PROCESS_MANAGER_SUPERVISOR_STARTSECS', 1),
            'user' => env('PROCESS_MANAGER_SUPERVISOR_USER'),
            'tail_lines' => env('PROCESS_MANAGER_SUPERVISOR_TAIL_LINES', 200),
        ],

        'systemd' => [
            'binary' => env('PROCESS_MANAGER_SYSTEMD_BINARY', 'systemctl'),
            'use_sudo' => env('PROCESS_MANAGER_SYSTEMD_USE_SUDO', false),
            'sudo_binary' => env('PROCESS_MANAGER_SYSTEMD_SUDO_BINARY', 'sudo'),
            'sudo_non_interactive' => env('PROCESS_MANAGER_SYSTEMD_SUDO_NON_INTERACTIVE', true),
            'scope' => env('PROCESS_MANAGER_SYSTEMD_SCOPE', 'system'),
            'unit_path' => env('PROCESS_MANAGER_SYSTEMD_UNIT_PATH'),
            'working_directory' => env('PROCESS_MANAGER_SYSTEMD_WORKING_DIRECTORY'),
            'restart' => env('PROCESS_MANAGER_SYSTEMD_RESTART', 'always'),
            'user' => env('PROCESS_MANAGER_SYSTEMD_USER'),
            'enable_on_create' => env('PROCESS_MANAGER_SYSTEMD_ENABLE_ON_CREATE', false),
            'tail_lines' => env('PROCESS_MANAGER_SYSTEMD_TAIL_LINES', 200),
        ],
    ],
];
