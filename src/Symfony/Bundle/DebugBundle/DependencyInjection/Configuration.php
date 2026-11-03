<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DebugBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @deprecated since Symfony 8.2, the bundle uses the more modern way to load its configuration in the main bundle class
 *
 * DebugExtension configuration structure.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        throw new \LogicException('Since Symfony 8.2, the bundle uses the more modern way to load its configuration in the main bundle class.');
    }
}
