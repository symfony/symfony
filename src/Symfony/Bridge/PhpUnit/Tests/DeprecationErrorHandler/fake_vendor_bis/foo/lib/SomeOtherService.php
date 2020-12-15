<?php

namespace foo\lib;

class SomeOtherService
{
    public function deprecatedApi()
    {
        @trigger_error(
            __FUNCTION__.' from foo is deprecated! You should stop relying on it!',
            \E_USER_DEPRECATED
        );
    }
}
