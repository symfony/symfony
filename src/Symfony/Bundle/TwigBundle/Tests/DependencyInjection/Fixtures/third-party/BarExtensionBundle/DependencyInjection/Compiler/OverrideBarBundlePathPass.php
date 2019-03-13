<?php

namespace ThirdParty\BarExtensionBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use ThirdParty\BarExtensionBundle\BarExtensionBundle;

class OverrideBarBundlePathPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $twigLoaderFilesystemDefinition = $container->findDefinition('twig.loader.filesystem');

        // Replaces native Bar templates
        $barExtensionBundleRefl = new \ReflectionClass(BarExtensionBundle::class);
        if ($barExtensionBundleRefl->isUserDefined()) {
            $barExtensionBundlePath = \dirname((string) $barExtensionBundleRefl->getFileName());
            $barExtensionBundleTwigPath = $barExtensionBundlePath.'/Resources/views';
            $twigLoaderFilesystemDefinition->addMethodCall(
                'addPath',
                [$barExtensionBundleTwigPath, 'Bar']
            );
        }
    }
}
