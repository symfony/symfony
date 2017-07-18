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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Guilhem N. <egetick@gmail.com>
 *
 * @deprecated since version 3.3, to be removed in 4.0.
 */
class FactoryReturnTypePass implements CompilerPassInterface
{
    private $resolveClassPass;

    public function __construct(ResolveClassPass $resolveClassPass = null)
    {
        if (null === $resolveClassPass) {
            @trigger_error('The '.__CLASS__.' class is deprecated since version 3.3 and will be removed in 4.0.', E_USER_DEPRECATED);
        }
        $this->resolveClassPass = $resolveClassPass;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        // works only since php 7.0 and hhvm 3.11
        if (!method_exists(\ReflectionMethod::class, 'getReturnType')) {
            return;
        }
        $resolveClassPassChanges = null !== $this->resolveClassPass ? $this->resolveClassPass->getChanges() : array();

        foreach ($container->getDefinitions() as $id => $definition) {
            $this->updateDefinition($container, $id, $definition, $resolveClassPassChanges);
        }
    }

    private function updateDefinition(ContainerBuilder $container, $id, Definition $definition, array $resolveClassPassChanges, array $previous = array())
    {
        // circular reference
        $lcId = strtolower($id);
        if (isset($previous[$lcId])) {
            return;
        }

        $factory = $definition->getFactory();
        if (null === $factory || (!isset($resolveClassPassChanges[$lcId]) && null !== $definition->getClass())) {
            return;
        }

        $class = null;
        if (is_string($factory)) {
            try {
                $m = new \ReflectionFunction($factory);
                if (false !== $m->getFileName() && file_exists($m->getFileName())) {
                    $container->fileExists($m->getFileName());
                }
            } catch (\ReflectionException $e) {
                return;
            }
        } else {
            if ($factory[0] instanceof Reference) {
                $previous[$lcId] = true;
                $factoryDefinition = $container->findDefinition((string) $factory[0]);
                $this->updateDefinition($container, $factory[0], $factoryDefinition, $resolveClassPassChanges, $previous);
                $class = $factoryDefinition->getClass();
            } else {
                $class = $factory[0];
            }

            if (!$m = $container->getReflectionClass($class, false)) {
                return;
            }
            try {
                $m = $m->getMethod($factory[1]);
            } catch (\ReflectionException $e) {
                return;
            }
        }

        $returnType = $m->getReturnType();
        if (null !== $returnType && !$returnType->isBuiltin()) {
            $returnType = $returnType instanceof \ReflectionNamedType ? $returnType->getName() : $returnType->__toString();
            if (null !== $class) {
                $declaringClass = $m->getDeclaringClass()->getName();
                if ('self' === strtolower($returnType)) {
                    $returnType = $declaringClass;
                } elseif ('parent' === strtolower($returnType)) {
                    $returnType = get_parent_class($declaringClass) ?: null;
                }
            }

            if (null !== $returnType && (!isset($resolveClassPassChanges[$lcId]) || $returnType !== $resolveClassPassChanges[$lcId])) {
                @trigger_error(sprintf('Relying on its factory\'s return-type to define the class of service "%s" is deprecated since Symfony 3.3 and won\'t work in 4.0. Set the "class" attribute to "%s" on the service definition instead.', $id, $returnType), E_USER_DEPRECATED);
            }
            $definition->setClass($returnType);
        }
    }
}
