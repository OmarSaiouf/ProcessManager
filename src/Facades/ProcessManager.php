<?php 

namespace OmarSaiouf\ProcessManager\Facades;

class ProcessManager extends \Illuminate\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return \OmarSaiouf\ProcessManager\Contracts\ProcessManagerInterface::class;
    }
}