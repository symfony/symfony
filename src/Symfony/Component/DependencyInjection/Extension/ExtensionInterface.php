<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Extension;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * ExtensionInterface is the interface implemented by container extension classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface ExtensionInterface
{
    /**
     * Loads a specific configuration.
     *
     * @param array<array<mixed>> $configs
     *
     * @throws \InvalidArgumentException When provided tag is not defined in this extension
     */
    public function load(array $configs, ContainerBuilder $container): void;

    /**
     * Returns the recommended alias to use in XML.
     *
     * This alias is also the mandatory prefix to use when using YAML.
     */
    public function getAlias(): string;
}
