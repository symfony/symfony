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

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureResourceTag;

#[AutoconfigureResourceTag('foo_bar', attributes: static function (string $class): array {
    return ['foo' => $class::getKey()];
})]
interface ResourceTaggedWithClosureInterface
{
    public static function getKey(): string;
}
