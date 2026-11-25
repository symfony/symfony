<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Css;

use Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException;

/** @internal */
final readonly class FunctionEvaluator
{
    private const MAX_RESULT_BYTES = 65536;

    /**
     * @param \Closure(string, array): string $substitute
     * @param \Closure(string, string): bool  $matches
     * @param \Closure(array): \Closure       $conditions
     */
    public function __construct(private array $functions, private \Closure $substitute, private \Closure $matches, private \Closure $conditions)
    {
    }

    public function resolve(string $value, array $custom, int $depth = 0): string
    {
        if (!$this->functions || !str_contains($value, '--') || !preg_match('/--[\w-]+\(/', $value)) {
            return $value;
        }
        if ($depth > 16) {
            throw new InvalidArgumentException('Custom functions call each other in a cycle.');
        }
        $offset = 0;
        while (null !== $start = ValueTokenizer::findOutsideStrings($value, '/(?<![\w-])(--[\w-]+)\(/', $offset)) {
            preg_match('/--[\w-]+/', $value, $name, 0, $start);
            $name = $name[0];
            $open = $start + \strlen($name);
            $end = ValueTokenizer::closingParenthesis($value, $open);
            if (null === $function = $this->functions[$name] ?? null) {
                $offset = $open;
                continue;
            }
            $inner = substr($value, $open + 1, $end - $open - 1);
            $arguments = '' === trim($inner) ? [] : array_map(fn (string $argument) => trim($this->resolve($argument, $custom, $depth + 1)), Selector::splitTopLevel($inner));
            if (\count($arguments) > \count($function['params'])) {
                throw new InvalidArgumentException(\sprintf('Too many arguments for "%s()".', $name));
            }
            // The custom properties of the element, then the parameters, then the local ones
            $scope = $custom;
            foreach ($function['params'] as $index => [$param, $type, $default]) {
                $argument = $arguments[$index] ?? (null !== $default ? $this->resolve(($this->substitute)($default, $scope), $scope, $depth + 1) : null);
                // Without an argument nor a default, the parameter is not set: var() of it takes its fallback
                if (null === $argument) {
                    unset($scope[$param]);
                    continue;
                }
                if (null !== $type && !($this->matches)($type, $argument)) {
                    throw new InvalidArgumentException(\sprintf('Invalid argument "%s" of "%s()".', $param, $name));
                }
                $scope[$param] = $argument;
            }
            $test = ($this->conditions)($scope);
            $result = null;
            foreach ($function['body'] as [$conditions, $property, $declared]) {
                foreach ($conditions as [$kind, $condition]) {
                    if (!$test($kind, $condition)) {
                        continue 2;
                    }
                }
                try {
                    $resolved = Conditional::resolve($this->resolve(($this->substitute)($declared, $scope), $scope, $depth + 1), $test);
                } catch (InvalidArgumentException) {
                    // An invalid descriptor is ignored, like an invalid declaration
                    continue;
                }
                if ('result' === $property) {
                    $result = $resolved;
                } else {
                    $scope[$property] = $resolved;
                }
            }
            if (null === $result || null !== $function['returns'] && !($this->matches)($function['returns'], $result)) {
                throw new InvalidArgumentException(\sprintf('"%s()" has no result of its type.', $name));
            }
            if (\strlen($result) > self::MAX_RESULT_BYTES - (\strlen($value) - ($end - $start + 1))) {
                throw new InvalidArgumentException('Custom functions exceed the computed value size limit.');
            }
            $value = substr($value, 0, $start).$result.substr($value, $end + 1);
            $offset = $start + \strlen($result);
        }

        return $value;
    }
}
