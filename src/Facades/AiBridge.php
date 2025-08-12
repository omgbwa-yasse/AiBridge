<?php

namespace AiBridge\Facades;

use Illuminate\Support\Facades\Facade;

class AiBridge extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'AiBridge';
    }
}
