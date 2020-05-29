<?php

namespace xnf4o\enaza\Facades;

use Illuminate\Support\Facades\Facade;

class enaza extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'enaza';
    }
}
