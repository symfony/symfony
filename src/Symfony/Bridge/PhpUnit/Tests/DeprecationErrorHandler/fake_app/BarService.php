<?php

namespace App\Services;

use acme\lib\ExtendsDeprecatedClassFromOtherVendor;

final class BarService
{
    public function __construct()
    {
        ExtendsDeprecatedClassFromOtherVendor::FOO;
    }
}
