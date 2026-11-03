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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * @deprecated since Symfony 8.2, the bundle uses the more modern way to load its extension in the main bundle class
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DebugExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        throw new \LogicException('Since Symfony 8.2, the bundle uses the more modern way to load its extension in the main bundle class.');
    }
}
