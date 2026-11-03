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

class ReadonlyPropertyTarget
{
    public readonly string $uninitialized;
    public readonly string $initialized;
    public string $name = '';

    public function __construct()
    {
        $this->initialized = 'target';
    }
}
