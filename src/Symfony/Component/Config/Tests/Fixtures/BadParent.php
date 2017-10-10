<?php

namespace Symfony\Component\Config\Tests\Fixtures;

if (!function_exists('__phpunit_run_isolated_test')) {
    class BadParent extends MissingParent
    {
    }
}
