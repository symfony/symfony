<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace bar\lib;

class AnotherService
{
    public function deprecatedApi(bool $useContracts = false)
    {
        $args = [__FUNCTION__, __FUNCTION__];
        if ($useContracts) {
            trigger_deprecation('bar/lib', '3.0', \sprintf('%s is deprecated, use %s_new instead.', ...$args));
        } else {
            @trigger_error(\sprintf('Since bar/lib 3.0: %s is deprecated, use %s_new instead.', ...$args), \E_USER_DEPRECATED);
        }
    }
}
