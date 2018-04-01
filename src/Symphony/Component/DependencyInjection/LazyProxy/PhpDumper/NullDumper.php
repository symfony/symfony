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
 * Null dumper, negates any proxy code generation for any given service definition.
 *
 * @author Marco Pivetta <ocramius@gmail.com>
 *
 * @final
 */
class NullDumper implements DumperInterface
{
    /**
     * {@inheritdoc}
     */
    public function isProxyCandidate(Definition $definition)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getProxyFactoryCode(Definition $definition, $id, $factoryCode = null)
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getProxyCode(Definition $definition)
    {
        return '';
    }
}
