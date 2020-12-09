<?php

namespace acme\lib;

use bar\lib\AnotherService;

class SomeService
{
    public function deprecatedApi(bool $useContracts = false)
    {
        $args = [__FUNCTION__, __FUNCTION__];
        if ($useContracts) {
            trigger_deprecation('acme/lib', '3.0', sprintf('%s is deprecated, use %s_new instead.', ...$args));
        } else {
            @trigger_error(sprintf('Since acme/lib 3.0: %s is deprecated, use %s_new instead.', ...$args), \E_USER_DEPRECATED);
        }
    }

    public function indirectDeprecatedApi(bool $useContracts = false)
    {
        (new AnotherService())->deprecatedApi($useContracts);
    }
}
