<?php

namespace App\Services;

use acme\lib\SomeService;
use foo\lib\SomeOtherService;

final class AppService
{
    public function directDeprecationsTwoVendors()
    {
        $service1 = new SomeService();
        $service1->deprecatedApi();

        $service2 = new SomeOtherService();
        $service2->deprecatedApi();
    }

    public function selfDeprecation(bool $useContracts = false)
    {
        $args = [__FUNCTION__, __FUNCTION__];
        if ($useContracts) {
            trigger_deprecation('App', '3.0', sprintf('%s is deprecated, use %s_new instead.', ...$args));
        } else {
            @trigger_error(sprintf('Since App 3.0: %s is deprecated, use %s_new instead.', ...$args), \E_USER_DEPRECATED);
        }
    }

    public function directDeprecation(bool $useContracts = false)
    {
        $service = new SomeService();
        $service->deprecatedApi($useContracts);
    }

    public function indirectDeprecation(bool $useContracts = false)
    {
        $service = new SomeService();
        $service->indirectDeprecatedApi($useContracts);
    }

    public function directDeprecations()
    {
        $service1 = new SomeService();
        $service1->deprecatedApi();

        $service2 = new SomeOtherService();
        $service2->deprecatedApi();
    }
}
