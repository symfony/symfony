<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
