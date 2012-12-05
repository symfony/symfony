<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\MergeExtensionConfigurationPass as BaseMergeExtensionConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Ensures certain extensions are always loaded.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class MergeExtensionConfigurationPass extends BaseMergeExtensionConfigurationPass
{
    private $extensions;
    private $prependingExtensions;

    public function __construct(array $extensions, array $prependingExtensions = array())
    {
        $this->extensions = $extensions;
        $this->prependingExtensions = $prependingExtensions;
    }

    public function process(ContainerBuilder $container)
    {
        foreach ($this->prependingExtensions as $extension) {
            /** @var \Symfony\Component\DependencyInjection\PrependExtensionInterface $extension */
            $extension->prepend($container);
        }

        foreach ($this->extensions as $extension) {
            if (!count($container->getExtensionConfig($extension))) {
                $container->loadFromExtension($extension, array());
            }
        }

        parent::process($container);
    }
}
