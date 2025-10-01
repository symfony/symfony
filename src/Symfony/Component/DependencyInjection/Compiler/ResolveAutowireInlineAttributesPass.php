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

use Symfony\Component\DependencyInjection\Attribute\AutowireInline;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\VarExporter\ProxyHelper;

/**
 * Inspects existing autowired services for {@see AutowireInline} attributes and registers the definitions for reuse.
 *
 * @author Ismail Özgün Turan <oezguen.turan@dadadev.com>
 */
class ResolveAutowireInlineAttributesPass extends AbstractRecursivePass
{
    protected bool $skipScalars = true;

    private int $counter;

    protected function processValue(mixed $value, bool $isRoot = false): mixed
    {
        $value = parent::processValue($value, $isRoot);

        if (!$value instanceof Definition || !$value->isAutowired() || !$value->getClass() || $value->hasTag('container.ignore_attributes')) {
            return $value;
        }

        if ($isRoot) {
            $this->counter = 0;
        }

        $isChildDefinition = $value instanceof ChildDefinition;

        try {
            $constructor = $this->getConstructor($value, false);
        } catch (RuntimeException) {
            return $value;
        }

        if ($constructor) {
            $arguments = $this->registerAutowireInlineAttributes($constructor, $value->getArguments(), $isChildDefinition);

            if ($arguments !== $value->getArguments()) {
                $value->setArguments($arguments);
            }
        }

        $methodCalls = $value->getMethodCalls();

        foreach ($methodCalls as $i => $call) {
            [$method, $arguments] = $call;

            try {
                $method = $this->getReflectionMethod($value, $method);
            } catch (RuntimeException) {
                continue;
            }

            $arguments = $this->registerAutowireInlineAttributes($method, $arguments, $isChildDefinition);

            if ($arguments !== $call[1]) {
                $methodCalls[$i][1] = $arguments;
            }
        }

        if ($methodCalls !== $value->getMethodCalls()) {
            $value->setMethodCalls($methodCalls);
        }

        return $value;
    }

    private function registerAutowireInlineAttributes(\ReflectionFunctionAbstract $method, array $arguments, bool $isChildDefinition): array
    {
        $parameters = $method->getParameters();

        if ($method->isVariadic()) {
            array_pop($parameters);
        }
        $paramResolverContainer = new ContainerBuilder($this->container->getParameterBag());

        foreach ($parameters as $index => $parameter) {
            if ($isChildDefinition) {
                $index = 'index_'.$index;
            }

            if (\array_key_exists('$'.$parameter->name, $arguments) || (\array_key_exists($index, $arguments) && '' !== $arguments[$index])) {
                $attribute = \array_key_exists('$'.$parameter->name, $arguments) ? $arguments['$'.$parameter->name] : $arguments[$index];
                if (!$attribute instanceof AutowireInline) {
                    continue;
                }
            } elseif (!$attribute = $parameter->getAttributes(AutowireInline::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null) {
                continue;
            } else {
                $attribute = $attribute->newInstance();
            }

            $type = ProxyHelper::exportType($parameter, true);

            if (!$type && isset($arguments[$index])) {
                continue;
            }

            $definition = $attribute->buildDefinition($attribute->value, $type, $parameter);

            $paramResolverContainer->setDefinition('.autowire_inline', $definition);
            (new ResolveParameterPlaceHoldersPass(false, false))->process($paramResolverContainer);

            $id = '.autowire_inline.'.$this->currentId.'.'.++$this->counter;

            $this->container->setDefinition($id, $definition);
            $arguments[$isChildDefinition ? '$'.$parameter->name : $index] = new Reference($id);

            if ($definition->isAutowired()) {
                $this->processValue($definition);
            }
        }

        return $arguments;
    }
}
