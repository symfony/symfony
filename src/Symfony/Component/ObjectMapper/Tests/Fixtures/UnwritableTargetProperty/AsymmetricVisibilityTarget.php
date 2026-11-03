<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\UnwritableTargetProperty;

class AsymmetricVisibilityTarget
{
    public private(set) string $privateSet = 'target';
    public protected(set) string $protectedSet = 'target';
    public public(set) readonly string $uninitialized;
    public public(set) readonly string $initialized;
    public string $virtual {
        get => 'target';
    }

    public function __construct()
    {
        $this->initialized = 'target';
    }
}
