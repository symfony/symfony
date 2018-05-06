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

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\AutowiringFailedException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Looks for definitions with autowiring enabled and parses their "@param" annotations for service ids and parameters.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class AutowireAnnotatedArgumentsPass extends AbstractRecursivePass
{
    private const PARAM_REGEX = '{
        (?:^/\*\*|\n\s*+\*)\s*+
        @param
        \s[^\$\*]*+
        \$([^\s\*]++)     # argument name
        \s++(
             @[^\s\*]++   # service id
            |%[^%\s\*]++% # parameter name
        )(?=[\s\*])
        }six';
    private const INHERITDOC_REGEX = '#(?:^/\*\*|\n\s*+\*)\s*+(?:\{@inheritdoc\}|@inheritdoc)(?:\s|\*/$)#i';

    /**
     * {@inheritdoc}
     */
    protected function processValue($value, $isRoot = false)
    {
        $value = parent::processValue($value, $isRoot);

        if (!$value instanceof Definition || !$value->isAutowired() || $value->isAbstract() || !$value->getClass()) {
            return $value;
        }
        if (!$classReflector = $this->container->getReflectionClass($value->getClass(), false)) {
            return $value;
        }

        try {
            if ($constructor = $this->getConstructor($value, false)) {
                $constructor = strtolower($constructor->name);
            }
        } catch (RuntimeException $e) {
            return $value;
        }
        if (!$annotatedParams = $this->getAnnotatedParams($classReflector, $constructor, $value)) {
            return $value;
        }
        $methodCalls = $value->getMethodCalls();

        if ($constructor) {
            array_unshift($methodCalls, array($constructor, $value->getArguments()));
        }

        foreach ($methodCalls as $i => $call) {
            list($method, $arguments) = $call;
            if (!isset($annotatedParams[$m = strtolower($method)])) {
                continue;
            }

            foreach ($annotatedParams[$m] as $j => $v) {
                if (!array_key_exists($j, $arguments) || ('' === $arguments[$j] && $v instanceof Reference)) {
                    $arguments[$j] = $v;
                }
            }
            ksort($arguments);

            if ($arguments !== $call[1]) {
                $methodCalls[$i][1] = $arguments;
            }
        }

        if ($constructor) {
            list(, $arguments) = array_shift($methodCalls);

            if ($arguments !== $value->getArguments()) {
                $value->setArguments($arguments);
            }
        }

        if ($methodCalls !== $value->getMethodCalls()) {
            $value->setMethodCalls($methodCalls);
        }

        return $value;
    }

    private function getAnnotatedParams(\ReflectionClass $classReflector, ?string $constructor, Definition $definition): array
    {
        $annotatedParams = array();

        if (null !== $constructor) {
            $annotatedParams[$constructor] = array();
        }
        foreach ($definition->getMethodCalls() as list($method)) {
            $annotatedParams[strtolower($method)] = array();
        }

        foreach ($classReflector->getMethods() as $methodReflector) {
            $r = $methodReflector;
            if (!isset($annotatedParams[$m = strtolower($r->name)])) {
                continue;
            }

            while (true) {
                if (false !== $doc = $r->getDocComment()) {
                    if (false !== stripos($doc, '@param') && preg_match_all(self::PARAM_REGEX, $doc, $params, PREG_SET_ORDER)) {
                        $paramIndexes = array();
                        foreach ($r->getParameters() as $i => $paramReflector) {
                            $paramIndexes[$paramReflector->name] = array($i, $paramReflector);
                        }
                        foreach ($params as list(, $k, $v)) {
                            if (!isset($paramIndexes[$k])) {
                                $this->container->log($this, sprintf('Skipping @param "$%s": no such argument on "%s::%s()".', $k, $r->class, $r->name));
                                continue;
                            }
                            list($i, $paramReflector) = $paramIndexes[$k];
                            if ('%' === $v[0] && $this->container->hasParameter($id = substr($v, 1, -1))) {
                                $v = $this->container->getParameter($id);
                            } elseif ('%' === $v[0]) {
                                throw new AutowiringFailedException($this->currentId, sprintf('Cannot autowire service "%s": parameter "%s" not found for argument "$%s" of method "%s()", you should either configure the argument explicitly or set the missing parameter.', $this->currentId, $id, $k, $r->class !== $this->currentId ? $r->class.'::'.$r->name : $r->name));
                            } elseif ($this->container->has($id = substr($v, 1))) {
                                $v = new Reference($id);
                            } elseif ($paramReflector->allowsNull() || $this->container->has(ProxyHelper::getTypeHint($classReflector, $paramReflector, true))) {
                                $this->container->log($this, sprintf('Skipping @param "$%s" on "%s::%s(): service "%s" not found.', $k, $r->class, $r->name, $id));
                                continue;
                            } else {
                                throw new AutowiringFailedException($this->currentId, sprintf('Cannot autowire service "%s": service "%s" not found for argument "$%s" of method "%s()", you should either configure the argument explicitly or define the missing service.', $this->currentId, $id, $k, $r->class !== $this->currentId ? $r->class.'::'.$r->name : $r->name));
                            }
                            $annotatedParams[$m] += array($i => $v);
                        }
                    }
                    if (false === stripos($doc, '@inheritdoc') || !preg_match(self::INHERITDOC_REGEX, $doc)) {
                        break;
                    }
                }
                try {
                    $r = $r->getPrototype();
                } catch (\ReflectionException $e) {
                    break; // method has no prototype
                }
            }
        }

        return array_filter($annotatedParams);
    }
}
