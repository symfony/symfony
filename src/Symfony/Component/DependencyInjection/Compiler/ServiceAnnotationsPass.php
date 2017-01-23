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

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\DependencyInjection\Argument\ClosureProxyArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Annotation as Annotations;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class ServiceAnnotationsPass implements CompilerPassInterface
{
    /**
     * @var AnnotationReader
     */
    private $reader;

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (class_exists(AnnotationReader::class)) {
            return;
        }

        $this->reader = new AnnotationReader();

        $annotatedServiceIds = $container->findTaggedServiceIds('annotated');

        foreach ($annotatedServiceIds as $annotatedServiceId => $params) {
            $this->augmentServiceDefinition($container->getDefinition($annotatedServiceId));
        }
    }

    private function augmentServiceDefinition(Definition $definition)
    {
        // 1) read class annotation for Definition
        $reflectionClass = new \ReflectionClass($definition->getClass());
        /** @var Annotations\Service $definitionAnnotation */
        $definitionAnnotation = $this->reader->getClassAnnotation(
            $reflectionClass,
            Annotations\Service::class
        );

        if ($definitionAnnotation) {
            if (null !== $definitionAnnotation->isShared()) {
                $definition->setShared($definitionAnnotation->isShared());
            }

            if (null !== $definitionAnnotation->isPublic()) {
                $definition->setPublic($definitionAnnotation->isPublic());
            }

            if (null !== $definitionAnnotation->isSynthetic()) {
                $definition->setSynthetic($definitionAnnotation->isSynthetic());
            }

            if (null !== $definitionAnnotation->isAbstract()) {
                $definition->setAbstract($definitionAnnotation->isAbstract());
            }

            if (null !== $definitionAnnotation->isLazy()) {
                $definition->setLazy($definitionAnnotation->isLazy());
            }

            // todo - add support for the other Definition properties
        }

        // 2) read Argument from __construct
        if ($constructor = $reflectionClass->getConstructor()) {
            $newArgs = $this->updateMethodArguments($definition, $constructor, $definition->getArguments());
            $definition->setArguments($newArgs);
        }
    }

    private function updateMethodArguments(Definition $definition, \ReflectionMethod $reflectionMethod, array $arguments)
    {
        $argAnnotations = $this->getArgumentAnnotationsForMethod($reflectionMethod);
        $argumentIndexes = $this->getMethodArguments($reflectionMethod);
        foreach ($argAnnotations as $arg) {
            if (!isset($argumentIndexes[$arg->getName()])) {
                throw new \InvalidArgumentException(sprintf('Invalid argument name "%s" used on the Argument annotation of %s::%s', $arg->getName(), $definition->getClass(), $reflectionMethod->getName()));
            }
            $key = $argumentIndexes[$arg->getName()];

            $onInvalid = $arg->getOnInvalid();
            $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
            if ('ignore' == $onInvalid) {
                $invalidBehavior = ContainerInterface::IGNORE_ON_INVALID_REFERENCE;
            } elseif ('null' == $onInvalid) {
                $invalidBehavior = ContainerInterface::NULL_ON_INVALID_REFERENCE;
            }

            $type = $arg->getType();
            // if "id" is set, default "type" to service
            if (!$type && $arg->getId()) {
                $type = 'service';
            }

            switch ($type) {
                case 'service':
                    $arguments[$key] = new Reference($arg->getId(), $invalidBehavior);
                    break;
                case 'expression':
                    $arguments[$key] = new Expression($arg->getValue());
                    break;
                case 'closure-proxy':
                    $arguments[$key] = new ClosureProxyArgument($arg->getId(), $arg->getMethod(), $invalidBehavior);
                    break;
                case 'collection':
                    // todo
                    break;
                case 'iterator':
                    // todo
                    break;
                case 'constant':
                    $arguments[$key] = constant(trim($arg->getValue()));
                    break;
                default:
                    $arguments[$key] = $arg->getValue();
            }
        }

        // it's possible index 1 was set, then index 0, then 2, etc
        // make sure that we re-order so they're injected as expected
        ksort($arguments);

        return $arguments;
    }

    /**
     * @param \ReflectionMethod $method
     *
     * @return Annotations\Argument[]
     */
    private function getArgumentAnnotationsForMethod(\ReflectionMethod $method)
    {
        $annotations = $this->reader->getMethodAnnotations($method);
        $argAnnotations = array();
        foreach ($annotations as $annotation) {
            if ($annotation instanceof Annotations\Argument) {
                $argAnnotations[] = $annotation;
            }
        }

        return $argAnnotations;
    }

    /**
     * Returns arguments to a method, where the key is the *name*
     * of the argument and the value is its index.
     *
     * @param \ReflectionMethod $method
     *
     * @return array
     */
    private function getMethodArguments(\ReflectionMethod $method)
    {
        $arguments = array();
        $i = 0;
        foreach ($method->getParameters() as $parameter) {
            $arguments[$parameter->getName()] = $i;

            ++$i;
        }

        return $arguments;
    }
}
