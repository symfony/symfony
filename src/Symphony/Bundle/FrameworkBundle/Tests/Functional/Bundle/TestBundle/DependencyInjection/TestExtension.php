<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\DependencyInjection;

use Symphony\Component\DependencyInjection\Alias;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Extension\Extension;
use Symphony\Component\DependencyInjection\Extension\PrependExtensionInterface;

class TestExtension extends Extension implements PrependExtensionInterface
{
    private $customConfig;

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        $container->setAlias('test.annotation_reader', new Alias('annotation_reader', true));
    }

    /**
     * {@inheritdoc}
     */
    public function prepend(ContainerBuilder $container)
    {
        $container->prependExtensionConfig('test', array('custom' => 'foo'));
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new Configuration($this->customConfig);
    }

    public function setCustomConfig($customConfig)
    {
        $this->customConfig = $customConfig;
    }
}
