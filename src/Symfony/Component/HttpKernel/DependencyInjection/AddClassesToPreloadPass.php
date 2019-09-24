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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Collects the classes to list in the preloading script.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class AddClassesToPreloadPass implements CompilerPassInterface
{
    use ClassMatchingTrait;

    private $kernel;
    private $classesToPreload = [];

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $preloadedClasses = $this->kernel->getClassesToPreload();
        foreach ($container->getExtensions() as $extension) {
            if ($extension instanceof Extension) {
                $preloadedClasses = array_merge($preloadedClasses, $extension->getClassesToPreload());
            }
        }

        $existingClasses = $this->getClassesInComposerClassMaps();

        $preloadedClasses = $container->getParameterBag()->resolveValue($preloadedClasses);
        $this->classesToPreload = $this->expandClasses($preloadedClasses, $existingClasses);
    }

    public function getClassesToPreload(): array
    {
        return $this->classesToPreload;
    }
}
