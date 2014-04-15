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
    public function getProxyFactoryCode(Definition $definition, $id)
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
