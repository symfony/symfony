<?php

namespace App\Services;

use acme\lib\SomeService;
use foo\lib\SomeOtherService;

final class AppService
{
    public function directDeprecations()
    {
        $service1 = new SomeService();
        $service1->deprecatedApi();

        $service2 = new SomeOtherService();
        $service2->deprecatedApi();
    }
}
