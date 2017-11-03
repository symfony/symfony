<?php

namespace acme\lib;

class SomeService
{
    public function deprecatedApi()
    {
        @trigger_error(
            __FUNCTION__.' is deprecated! You should stop relying on it!',
            E_USER_DEPRECATED
        );
    }
}
