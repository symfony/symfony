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

use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\LazyProxy\ProxyHelper;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;

/**
 * @author Guilhem Niot <guilhem.niot@gmail.com>
 */
class ResolveBindingsPass extends AbstractRecursivePass
{
    private $usedBindings = [];
    private $unusedBindings = [];
    private $errorMessages = [];

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $this->usedBindings = $container->getRemovedBindingIds();

        try {
            parent::process($container);

            foreach ($this->unusedBindings as list($key, $serviceId, $bindingType, $file)) {
                $argumentType = $argumentName = $message = null;

                if (false !== strpos($key, ' ')) {
                    list($argumentType, $argumentName) = explode(' ', $key, 2);
                } elseif ('$' === $key[0]) {
                    $argumentName = $key;
                } else {
                    $argumentType = $key;
                }

                if ($argumentType) {
                    $message .= sprintf('of type "%s" ', $argumentType);
                }

                if ($argumentName) {
                    $message .= sprintf('named "%s" ', $argumentName);
                }

                if (BoundArgument::DEFAULTS_BINDING === $bindingType) {
                    $message .= 'under "_defaults"';
                } elseif (BoundArgument::INSTANCEOF_BINDING === $bindingType) {
                    $message .= 'under "_instanceof"';
                } else {
                    $message .= sprintf('for service "%s"', $serviceId);
                }

                if ($file) {
                    $message .= sprintf(' in file "%s"', $file);
                }

                $message = sprintf('A binding is configured for an argument %s, but no corresponding argument has been found. It may be unused and should be removed, or it may have a typo.', $message);

                if ($this->errorMessages) {
                    $message .= sprintf("\nCould be related to%s:", 1 < \count($this->errorMessages) ? ' one of' : '');
                }
                foreach ($this->errorMessages as $m) {
                    $message .= "\n - ".$m;
                }
                throw new InvalidArgumentException($message);
            }
        } finally {
            $this->usedBindings = [];
            $this->unusedBindings = [];
            $this->errorMessages = [];
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function processValue($value, $isRoot = false)
    {
        if ($value instanceof TypedReference && $value->getType() === (string) $value) {
            // Already checked
            $bindings = $this->container->getDefinition($this->currentId)->getBindings();
            $name = $value->getName();

            if (isset($name, $bindings[$name = $value.' $'.$name])) {
                return $this->getBindingValue($bindings[$name]);
            }

            if (isset($bindings[$value->getType()])) {
                return $this->getBindingValue($bindings[$value->getType()]);
            }

            return parent::processValue($value, $isRoot);
        }

        if (!$value instanceof Definition || !$bindings = $value->getBindings()) {
            return parent::processValue($value, $isRoot);
        }

        $bindingNames = [];

        foreach ($bindings as $key => $binding) {
            list($bindingValue, $bindingId, $used, $bindingType, $file) = $binding->getValues();
            if ($used) {
                $this->usedBindings[$bindingId] = true;
                unset($this->unusedBindings[$bindingId]);
            } elseif (!isset($this->usedBindings[$bindingId])) {
                $this->unusedBindings[$bindingId] = [$key, $this->currentId, $bindingType, $file];
            }

            if (preg_match('/^(?:(?:array|bool|float|int|string|([^ $]++)) )\$/', $key, $m)) {
                $bindingNames[substr($key, \strlen($m[0]))] = $binding;
            }

            if (!isset($m[1])) {
                continue;
            }

            if (null !== $bindingValue && !$bindingValue instanceof Reference && !$bindingValue instanceof Definition && !$bindingValue instanceof TaggedIteratorArgument && !$bindingValue instanceof ServiceLocatorArgument) {
                throw new InvalidArgumentException(sprintf('Invalid value for binding key "%s" for service "%s": expected null, "%s", "%s", "%s" or ServiceLocatorArgument, "%s" given.', $key, $this->currentId, Reference::class, Definition::class, TaggedIteratorArgument::class, \gettype($bindingValue)));
            }
        }

        if ($value->isAbstract()) {
            return parent::processValue($value, $isRoot);
        }

        $calls = $value->getMethodCalls();

        try {
            if ($constructor = $this->getConstructor($value, false)) {
                $calls[] = [$constructor, $value->getArguments()];
            }
        } catch (RuntimeException $e) {
            $this->errorMessages[] = $e->getMessage();
            $this->container->getDefinition($this->currentId)->addError($e->getMessage());

            return parent::processValue($value, $isRoot);
        }

        foreach ($calls as $i => $call) {
            list($method, $arguments) = $call;

            if ($method instanceof \ReflectionFunctionAbstract) {
                $reflectionMethod = $method;
            } else {
                try {
                    $reflectionMethod = $this->getReflectionMethod($value, $method);
                } catch (RuntimeException $e) {
                    if ($value->getFactory()) {
                        continue;
                    }
                    throw $e;
                }
            }

            foreach ($reflectionMethod->getParameters() as $key => $parameter) {
                if (\array_key_exists($key, $arguments) && '' !== $arguments[$key]) {
                    continue;
                }

                $typeHint = ProxyHelper::getTypeHint($reflectionMethod, $parameter);

                if (\array_key_exists($k = ltrim($typeHint, '\\').' $'.$parameter->name, $bindings)) {
                    $arguments[$key] = $this->getBindingValue($bindings[$k]);

                    continue;
                }

                if (\array_key_exists('$'.$parameter->name, $bindings)) {
                    $arguments[$key] = $this->getBindingValue($bindings['$'.$parameter->name]);

                    continue;
                }

                if ($typeHint && '\\' === $typeHint[0] && isset($bindings[$typeHint = substr($typeHint, 1)])) {
                    $arguments[$key] = $this->getBindingValue($bindings[$typeHint]);

                    continue;
                }

                if (isset($bindingNames[$parameter->name])) {
                    $bindingKey = array_search($binding, $bindings, true);
                    $argumentType = substr($bindingKey, 0, strpos($bindingKey, ' '));
                    $this->errorMessages[] = sprintf('Did you forget to add the type "%s" to argument "$%s" of method "%s::%s()"?', $argumentType, $parameter->name, $reflectionMethod->class, $reflectionMethod->name);
                }
            }

            if ($arguments !== $call[1]) {
                ksort($arguments);
                $calls[$i][1] = $arguments;
            }
        }

        if ($constructor) {
            list(, $arguments) = array_pop($calls);

            if ($arguments !== $value->getArguments()) {
                $value->setArguments($arguments);
            }
        }

        if ($calls !== $value->getMethodCalls()) {
            $value->setMethodCalls($calls);
        }

        return parent::processValue($value, $isRoot);
    }

    /**
     * @return mixed
     */
    private function getBindingValue(BoundArgument $binding)
    {
        list($bindingValue, $bindingId) = $binding->getValues();

        $this->usedBindings[$bindingId] = true;
        unset($this->unusedBindings[$bindingId]);

        return $bindingValue;
    }
}
