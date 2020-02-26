<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\DependencyInjection;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Translation\TranslatorInterface as LegacyTranslatorInterface;
use Symfony\Component\Validator\Util\LegacyTranslatorProxy;

/**
 * @author Fabien Potencier <fabien@symfony.com>
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

        $initializers = [];
        foreach ($container->findTaggedServiceIds($this->initializerTag, true) as $id => $attributes) {
            $initializers[] = new Reference($id);
        }

        $container->getDefinition($this->builderService)->addMethodCall('addObjectInitializers', [$initializers]);

        // @deprecated logic, to be removed in Symfony 5.0
        $builder = $container->getDefinition($this->builderService);
        $calls = [];

        foreach ($builder->getMethodCalls() as [$method, $arguments]) {
            if ('setTranslator' === $method) {
                if (!$arguments[0] instanceof Reference) {
                    $translator = $arguments[0];
                } elseif ($container->has($arguments[0])) {
                    $translator = $container->findDefinition($arguments[0]);
                } else {
                    continue;
                }

                while (!($class = $translator->getClass()) && $translator instanceof ChildDefinition) {
                    $translator = $container->findDefinition($translator->getParent());
                }

                if (!is_subclass_of($class, LegacyTranslatorInterface::class)) {
                    $arguments[0] = (new Definition(LegacyTranslatorProxy::class))->addArgument($arguments[0]);
                }
            }

            $calls[] = [$method, $arguments];
        }

        $builder->setMethodCalls($calls);
    }
}
