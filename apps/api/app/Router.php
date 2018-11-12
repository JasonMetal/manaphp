<?php
namespace App;

use App\Controllers\CustomerController;
use App\Controllers\TimeController;

class Router extends \ManaPHP\Router
{
    public function __construct()
    {
        parent::__construct(false);
        $this->add('/', [TimeController::class, 'current']);
        $this->add('/time/current', [TimeController::class, 'current']);
        $this->add('/time/timestamp', [TimeController::class, 'timestamp']);
        $this->addRest('/customers', CustomerController::class);
        $this->addOptions();
    }
}