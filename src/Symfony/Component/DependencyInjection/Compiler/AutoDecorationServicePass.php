<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\DecorationPriorityAwareInterface;
use Symfony\Component\DependencyInjection\DecoratorInterface;

/**
 * Adds decorated service to definition of services implementing DecoratorInterface.
 *
 * @author Grégory SURACI <gregory.suraci@free.fr>
 */
class AutoDecorationServicePass implements CompilerPassInterface
{
    private $throwOnException;

    public function __construct(bool $throwOnException = true)
    {
        $this->throwOnException = $throwOnException;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        foreach ($container->getDefinitions() as $definition) {
            $className = $definition->getClass();

            if (null === $className) {
                continue;
            }

            try {
                $classInterfaces = class_implements($className);

                if (!\in_array(DecoratorInterface::class, $classInterfaces)) {
                    continue;
                }

                if (\in_array(DecorationPriorityAwareInterface::class, $classInterfaces)) {
                    $definition->setDecoratedService(
                        $className::getDecoratedServiceId(),
                        null,
                        $className::getDecorationPriority()
                    );

                    continue;
                }

                $definition->setDecoratedService($className::getDecoratedServiceId());
            } catch (\Throwable $e) {
                if ($this->throwOnException) {
                    throw $e;
                }

                continue;
            }
        }
    }
}
