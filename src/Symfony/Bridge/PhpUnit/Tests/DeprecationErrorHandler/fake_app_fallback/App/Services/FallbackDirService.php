<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Services;

final class FallbackDirService
{
    public function selfDeprecation(): void
    {
        @trigger_error('Since FallbackApp 1.0: selfDeprecation is deprecated.', \E_USER_DEPRECATED);
    }
}
