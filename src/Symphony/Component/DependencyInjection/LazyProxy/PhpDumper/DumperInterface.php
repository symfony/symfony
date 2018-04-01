<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\LazyProxy\PhpDumper;

use Symphony\Component\DependencyInjection\Definition;

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
     * @return bool
     */
    public function isProxyCandidate(Definition $definition);

    /**
     * Generates the code to be used to instantiate a proxy in the dumped factory code.
     *
     * @param Definition $definition
     * @param string     $id          Service identifier
     * @param string     $factoryCode The code to execute to create the service
     *
     * @return string
     */
    public function getProxyFactoryCode(Definition $definition, $id, $factoryCode);

    /**
     * Generates the code for the lazy proxy.
     *
     * @return string
     */
    public function getProxyCode(Definition $definition);
}
