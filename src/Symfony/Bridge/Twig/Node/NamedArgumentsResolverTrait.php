<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Node;

use Twig\Error\SyntaxError;
use Twig\Node\Expression\ConstantExpression;

/**
 * @internal
 */
trait NamedArgumentsResolverTrait
{
    /**
     * @param string   $functionName
     * @param string[] $parametersNames
     *
     * @return array
     */
    private function resolveNamedArguments($functionName, array $parametersNames)
    {
        $arguments = array();

        $hasNamedArguments = false;
        foreach (iterator_to_array($this->getNode('arguments')) as $parameter => $node) {
            if (!is_int($parameter)) {
                $hasNamedArguments = true;

                if (false === $position = array_search($parameter, $parametersNames, true)) {
                    throw new \LogicException(sprintf('Unknown argument "%s" for function "%s".', $parameter, $functionName));
                }

                if (array_key_exists($position, $arguments)) {
                    throw new SyntaxError(sprintf('Argument "%s" is defined twice for function "%s".', $parameter, $functionName));
                }

                $arguments[$position] = $node;

                continue;
            }

            if ($hasNamedArguments) {
                throw new SyntaxError(sprintf('Positional arguments cannot be used after named arguments for function "%s".', $functionName));
            }

            $arguments[] = $node;
        }

        if ($hasNamedArguments) {
            for ($position = max(array_keys($arguments)) - 1; $position >= 0; --$position) {
                if (!array_key_exists($position, $arguments)) {
                    $arguments[$position] = new ConstantExpression(null, 0);
                }
            }

            ksort($arguments);
        }

        return $arguments;
    }
}
