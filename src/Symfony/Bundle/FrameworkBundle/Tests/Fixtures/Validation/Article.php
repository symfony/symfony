<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Validation;

if (!function_exists('__phpunit_run_isolated_test')) {
    class Article implements NotExistingInterface
    {
        public $category;
    }
}
