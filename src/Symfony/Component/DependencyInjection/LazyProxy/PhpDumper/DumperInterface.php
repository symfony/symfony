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
 * Lazy proxy dumper capable of generating the instantiation logic PHP code for proxied services.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 */
interface DumperInterface
{
    /**
     * Inspects whether the given definitions should produce proxy instantiation logic in the dumped container.
     *
     * @param bool|null   &$asGhostObject Set to true after the call if the proxy is a ghost object
     * @param string|null $id
     */
    public function isProxyCandidate(Definition $definition/* , bool &$asGhostObject = null, string $id = null */): bool;

    /**
     * Generates the code to be used to instantiate a proxy in the dumped factory code.
     */
    public function getProxyFactoryCode(Definition $definition, string $id, string $factoryCode): string;

    /**
     * Generates the code for the lazy proxy.
     */
    public function getProxyCode(Definition $definition/* , string $id = null */): string;
}
