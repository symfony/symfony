<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\LazyProxy\PhpDumper;

use Symfony\Component\DependencyInjection\Definition;

/**
 * Null dumper, negates any proxy code generation for any given service definition.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 *
 * @final
 */
class NullDumper implements DumperInterface
{
    public function isProxyCandidate(Definition $definition, ?bool &$asGhostObject = null, ?string $id = null): bool
    {
        return $asGhostObject = false;
    }

    public function getProxyFactoryCode(Definition $definition, string $id, string $factoryCode): string
    {
        return '';
    }

    public function getProxyCode(Definition $definition, ?string $id = null): string
    {
        return '';
    }
}
