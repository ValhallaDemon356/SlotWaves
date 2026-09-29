<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function artisan($command, $parameters = [])
    {
        if ($command === 'migrate:fresh' || (is_string($command) && str_starts_with($command, 'migrate:fresh'))) {
            $parameters['--force'] = true;
        }
        return parent::artisan($command, $parameters);
    }
}
