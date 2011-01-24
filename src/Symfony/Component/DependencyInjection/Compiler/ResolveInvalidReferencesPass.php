<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Emulates the invalid behavior if the reference is not found within the
 * container.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ResolveInvalidReferencesPass implements CompilerPassInterface
{
    protected $container;
    protected $exceptions;

    public function __construct(array $exceptions = array('kernel', 'service_container', 'templating.loader.wrapped', 'pdo_connection'))
    {
        $this->exceptions = $exceptions;
    }

    public function addException($id)
    {
        $this->exceptions[] = $id;
    }

    public function process(ContainerBuilder $container)
    {
        $this->container = $container;
        foreach ($container->getDefinitions() as $definition) {
            if ($definition->isSynthetic()) {
                continue;
            }

            $definition->setArguments(
                $this->processArguments($definition->getArguments())
            );

            $calls = array();
            foreach ($definition->getMethodCalls() as $call) {
                try {
                    $calls[] = array($call[0], $this->processArguments($call[1], true));
                } catch (\RuntimeException $ignore) {
                    // this call is simply removed
                }
            }
            $definition->setMethodCalls($calls);
        }
    }

    protected function processArguments(array $arguments, $inMethodCall = false)
    {
        foreach ($arguments as $k => $argument) {
            if (is_array($argument)) {
                $arguments[$k] = $this->processArguments($argument, $inMethodCall);
            } else if ($argument instanceof Reference) {
                $id = (string) $argument;

                if (in_array($id, $this->exceptions, true)) {
                    continue;
                }

                $invalidBehavior = $argument->getInvalidBehavior();
                $exists = $this->container->has($id);

                if ($exists && ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE !== $invalidBehavior) {
                    $arguments[$k] = new Reference($id);
                } else if (!$exists && ContainerInterface::NULL_ON_INVALID_REFERENCE === $invalidBehavior) {
                    $arguments[$k] = null;
                } else if (!$exists && ContainerInterface::IGNORE_ON_INVALID_REFERENCE === $invalidBehavior) {
                    if ($inMethodCall) {
                        throw new \RuntimeException('Method shouldn\'t be called.');
                    }

                    $arguments[$k] = null;
                }
            }
        }

        return $arguments;
    }
}