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

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\LazyProxy\ProxyHelper;

/**
 * Resolves iterator arguments needed to be an array by definition.
 *
 * @author Roland Franssen <franssen.rolandd@gmail.com>
 */
class ResolveArrayIterablesPass extends AbstractRecursivePass
{
    /**
     * {@inheritdoc}
     */
    protected function processValue($value, $isRoot = false)
    {
        if (!$value instanceof Definition || !$reflectionClass = $this->container->getReflectionClass($value->getClass())) {
            return parent::processValue($value, $isRoot);
        }

        $calls = $value->getMethodCalls();
        $calls[] = array('__construct', $value->getArguments());

        foreach ($calls as $i => $call) {
            list($method, $arguments) = $call;

            if (!$reflectionClass->hasMethod($method)) {
                continue;
            }

            $reflectionMethod = $reflectionClass->getMethod($method);
            $reflectionParams = $reflectionMethod->getParameters();

            foreach ($arguments as $key => $argument) {
                if (!$argument instanceof IteratorArgument || !isset($reflectionParams[$key])) {
                    continue;
                }

                if ('array' === ProxyHelper::getTypeHint($reflectionMethod, $reflectionParams[$key])) {
                    $calls[$i][1][$key] = $argument->getValues();
                }
            }
        }

        $value->setArguments(array_pop($calls)[1]);
        $value->setMethodCalls($calls);

        return $value;
    }
}
