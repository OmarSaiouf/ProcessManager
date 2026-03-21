<?php

use Illuminate\Support\Facades\Route;
use OmarSaiouf\ProcessManager\Facades\ProcessManager;

Route::get('/', function () {
    // ProcessManager::create('my-session', 'echo "Hello, World!" && sleep 60');  
    $captureOutput = ProcessManager::captureOutput('my-session');
    dd($captureOutput);
    return ProcessManager::all(); 
});
