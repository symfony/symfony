<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\ControllerMetadata;

use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\Types\Array_;
use phpDocumentor\Reflection\Types\ContextFactory;
use phpDocumentor\Reflection\Types\Object_;

/**
 * Builds {@see ArgumentMetadata} objects based on the given Controller.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class ArgumentMetadataFactory implements ArgumentMetadataFactoryInterface
{
    /**
     * If the ...$arg functionality is available.
     *
     * Requires at least PHP 5.6.0 or HHVM 3.9.1
     *
     * @var bool
     */
    private $supportsVariadic;

    /**
     * If the reflection supports the getType() method to resolve types.
     *
     * Requires at least PHP 7.0.0 or HHVM 3.11.0
     *
     * @var bool
     */
    private $supportsParameterType;

    private $docBlockFactory;
    private $contextFactory;

    public function __construct()
    {
        $this->supportsVariadic = method_exists('ReflectionParameter', 'isVariadic');
        $this->supportsParameterType = method_exists('ReflectionParameter', 'getType');
    }

    /**
     * {@inheritdoc}
     */
    public function createArgumentMetadata($controller)
    {
        $arguments = array();

        if (is_array($controller)) {
            $reflection = new \ReflectionMethod($controller[0], $controller[1]);
        } elseif (is_object($controller) && !$controller instanceof \Closure) {
            $reflection = (new \ReflectionObject($controller))->getMethod('__invoke');
        } else {
            $reflection = new \ReflectionFunction($controller);
        }

        foreach ($reflection->getParameters() as $param) {
            $tag = $this->getParamTag($param);
            $arguments[$param->getName()] = new ArgumentMetadata($param->getName(), $this->getType($param, $tag), $this->isVariadic($param), $this->hasDefaultValue($param), $this->getDefaultValue($param), $param->allowsNull(), $this->isArray($param, $tag));
        }

        return $arguments;
    }

    /**
     * Returns whether an argument is variadic.
     *
     * @return bool
     */
    private function isVariadic(\ReflectionParameter $parameter)
    {
        return $this->supportsVariadic && $parameter->isVariadic();
    }

    /**
     * Determines whether an argument has a default value.
     *
     * @return bool
     */
    private function hasDefaultValue(\ReflectionParameter $parameter)
    {
        return $parameter->isDefaultValueAvailable();
    }

    /**
     * Returns if the argument is an array.
     *
     * @return bool
     */
    private function isArray(\ReflectionParameter $parameter, Type $tag = null)
    {
        return $parameter->isArray() || ($tag && $tag instanceof Array_);
    }

    /**
     * Returns a default value if available.
     *
     * @return mixed|null
     */
    private function getDefaultValue(\ReflectionParameter $parameter)
    {
        return $this->hasDefaultValue($parameter) ? $parameter->getDefaultValue() : null;
    }

    /**
     * Returns an associated type to the given parameter if available.
     *
     * @return null|string
     */
    private function getType(\ReflectionParameter $parameter, Type $tag = null)
    {
        if ($this->supportsParameterType) {
            if (!$type = $parameter->getType()) {
                return null !== $tag ? $this->extractType($tag) : null;
            }
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : $type->__toString();
            if ('array' === $typeName && !$type->isBuiltin()) {
                // Special case for HHVM with variadics
                return;
            }

            return $typeName;
        }

        if (preg_match('/^(?:[^ ]++ ){4}([a-zA-Z_\x7F-\xFF][^ ]++)/', $parameter, $info)) {
            return $info[1];
        }

        if (null !== $tag) {
            return $this->extractType($tag);
        }
    }

    /**
     * @return Type
     */
    private function getParamTag(\ReflectionParameter $parameter)
    {
        if (!class_exists('phpDocumentor\Reflection\DocBlockFactory')) {
            return;
        }

        // try to get type information from @param
        if (null === $this->docBlockFactory) {
            $this->docBlockFactory = DocBlockFactory::createInstance();
            $this->contextFactory = new ContextFactory();
        }
        $context = $this->contextFactory->createFromReflector($parameter);
        $docblock = $this->docBlockFactory->create($parameter->getDeclaringFunction()->getDocComment(), $context);

        $class = null;
        $isArray = false;
        foreach ($docblock->getTagsByName('param') as $param) {
            if ($parameter->getName() != $param->getVariableName()) {
                continue;
            }

            return $param->getType();
        }
    }

    private function extractType(Type $tag)
    {
        if ($tag instanceof Array_) {
            $tag = $tag->getValueType();
        }

        if ($tag instanceof Object_) {
            return ltrim((string) $tag->getFqsen(), '\\');
        }
    }
}
