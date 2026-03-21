<?php 


namespace OmarSaiouf\ProcessManager\DTOs;

class ScreenSession
{
    public function __construct(
        public string $id,
        public string $name,
        public string $status,
    ) {}
}