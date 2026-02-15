<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLoadedValue;

class LoadedValueService
{
    public function __construct(private ?LoadedValue $value = null)
    {
    }

    public function load(): void
    {
        $this->value = new LoadedValue(name: 'loaded');
    }

    public function get(): LoadedValue
    {
        return $this->value;
    }
}
