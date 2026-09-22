<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * Configuration interface.
 *
 * @author Victor Berchet <victor@suumit.com>
 */
interface ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     *
     * The tree must not depend on anything external, such as container parameters, env vars or the machine it is built on: the same tree must come out whatever the environment.
     *
     * @return TreeBuilder<'array'>
     */
    public function getConfigTreeBuilder(): TreeBuilder;
}
