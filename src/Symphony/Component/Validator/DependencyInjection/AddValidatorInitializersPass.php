<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\DependencyInjection;

use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * @author Fabien Potencier <fabien@symphony.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class AddValidatorInitializersPass implements CompilerPassInterface
{
    private $builderService;
    private $initializerTag;

    public function __construct(string $builderService = 'validator.builder', string $initializerTag = 'validator.initializer')
    {
        $this->builderService = $builderService;
        $this->initializerTag = $initializerTag;
    }

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->builderService)) {
            return;
        }

        $initializers = array();
        foreach ($container->findTaggedServiceIds($this->initializerTag, true) as $id => $attributes) {
            $initializers[] = new Reference($id);
        }

        $container->getDefinition($this->builderService)->addMethodCall('addObjectInitializers', array($initializers));
    }
}
