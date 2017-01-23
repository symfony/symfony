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
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Resolves named arguments to their corresponding numeric index.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class ResolveNamedArgumentsPass extends AbstractRecursivePass
{
    /**
     * {@inheritdoc}
     */
    protected function processValue($value, $isRoot = false)
    {
        if (!$value instanceof Definition) {
            return parent::processValue($value, $isRoot);
        }

        $parameterBag = $this->container->getParameterBag();

        if ($class = $value->getClass()) {
            $class = $parameterBag->resolveValue($class);
        }

        $calls = $value->getMethodCalls();
        $calls[] = array('__construct', $value->getArguments());

        foreach ($calls as $i => $call) {
            list($method, $arguments) = $call;
            $method = $parameterBag->resolveValue($method);
            $parameters = null;

            foreach ($arguments as $key => $argument) {
                if (is_int($key) || '' === $key || '$' !== $key[0]) {
                    continue;
                }

                $parameters = null !== $parameters ? $parameters : $this->getParameters($class, $method);

                foreach ($parameters as $j => $p) {
                    if ($key === '$'.$p->name) {
                        unset($arguments[$key]);
                        $arguments[$j] = $argument;

                        continue 2;
                    }
                }

                throw new InvalidArgumentException(sprintf('Unable to resolve service "%s": method "%s::%s" has no argument named "%s". Check your service definition.', $this->currentId, $class, $method, $key));
            }

            if ($arguments !== $call[1]) {
                ksort($arguments);
                $calls[$i][1] = $arguments;
            }
        }

        list(, $arguments) = array_pop($calls);

        if ($arguments !== $value->getArguments()) {
            $value->setArguments($arguments);
        }
        if ($calls !== $value->getMethodCalls()) {
            $value->setMethodCalls($calls);
        }

        return parent::processValue($value, $isRoot);
    }

    /**
     * @param string|null $class
     * @param string      $method
     *
     * @throws InvalidArgumentException
     *
     * @return array
     */
    private function getParameters($class, $method)
    {
        if (!$class) {
            throw new InvalidArgumentException(sprintf('Unable to resolve service "%s": the class is not set.', $this->currentId));
        }

        if (!$r = $this->container->getReflectionClass($class)) {
            throw new InvalidArgumentException(sprintf('Unable to resolve service "%s": class "%s" does not exist.', $this->currentId, $class));
        }

        if (!$r->hasMethod($method)) {
            throw new InvalidArgumentException(sprintf('Unable to resolve service "%s": method "%s::%s" does not exist.', $this->currentId, $class, $method));
        }

        $method = $r->getMethod($method);
        if (!$method->isPublic()) {
            throw new InvalidArgumentException(sprintf('Unable to resolve service "%s": method "%s::%s" must be public.', $this->currentId, $class, $method->name));
        }

        return $method->getParameters();
    }
}
