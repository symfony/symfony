<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(factory: '@=service("factory_for_autoconfigure").create()')]
class AutoconfigureWithExpressionFactory
{
    public function __construct(public readonly string $foo)
    {
    }
}
