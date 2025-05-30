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

#[Autoconfigure(bind: ['$foo' => 'foo'], factory: ['@factory_for_autoconfigure', 'createStatic'])]
class AutoconfigureWithInstanceExternalFactory
{
    public function __construct(public readonly string $foo)
    {
    }
}
