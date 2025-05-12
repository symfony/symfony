<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath;

use Symfony\Component\JsonPath\Exception\InvalidArgumentException;
use Symfony\Component\JsonPath\Exception\InvalidJsonStringInputException;
use Symfony\Component\JsonPath\Exception\JsonCrawlerException;
use Symfony\Component\JsonPath\Tokenizer\JsonPathToken;
use Symfony\Component\JsonPath\Tokenizer\JsonPathTokenizer;
use Symfony\Component\JsonPath\Tokenizer\TokenType;
use Symfony\Component\JsonStreamer\Read\Splitter;

/**
 * Crawls a JSON document using a JSON Path as described in the RFC 9535.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9535
 *
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @experimental
 */
final class JsonCrawler implements JsonCrawlerInterface
{
    private const RFC9535_FUNCTIONS = [
        'length' => true,
        'count' => true,
        'match' => true,
        'search' => true,
        'value' => true,
    ];

    /**
     * @param resource|string $raw
     */
    public function __construct(
        private readonly mixed $raw,
    ) {
        if (!\is_string($raw) && !\is_resource($raw)) {
            throw new InvalidArgumentException(\sprintf('Expected string or resource, got "%s".', get_debug_type($raw)));
        }
    }

    public function find(string|JsonPath $query): array
    {
        return $this->evaluate(\is_string($query) ? new JsonPath($query) : $query);
    }

    private function evaluate(JsonPath $query): array
    {
        try {
            $tokens = JsonPathTokenizer::tokenize($query);
            $json = $this->raw;

            if (\is_resource($this->raw)) {
                if (!class_exists(Splitter::class)) {
                    throw new \LogicException('The JsonStreamer package is required to evaluate a path against a resource. Try running "composer require symfony/json-streamer".');
                }

                $simplified = JsonPathUtils::findSmallestDeserializableStringAndPath(
                    $tokens,
                    $this->raw,
                );

                $tokens = $simplified['tokens'];
                $json = $simplified['json'];
            }

            try {
                $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new InvalidJsonStringInputException($e->getMessage(), $e);
            }

            $current = [$data];

            foreach ($tokens as $token) {
                $next = [];
                foreach ($current as $value) {
                    $result = $this->evaluateToken($token, $value);
                    $next = array_merge($next, $result);
                }

                $current = $next;
            }

            return $current;
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new JsonCrawlerException($query, $e->getMessage(), previous: $e);
        }
    }

    private function evaluateToken(JsonPathToken $token, mixed $value): array
    {
        return match ($token->type) {
            TokenType::Name => $this->evaluateName($token->value, $value),
            TokenType::Bracket => $this->evaluateBracket($token->value, $value),
            TokenType::Recursive => $this->evaluateRecursive($value),
        };
    }

    private function evaluateName(string $name, mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        if ('*' === $name) {
            return array_values($value);
        }

        return \array_key_exists($name, $value) ? [$value[$name]] : [];
    }

    private function evaluateBracket(string $expr, mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        if ('*' === $expr) {
            return array_values($value);
        }

        // single negative index
        if (preg_match('/^-\d+$/', $expr)) {
            if (!array_is_list($value)) {
                return [];
            }

            $index = \count($value) + (int) $expr;

            return isset($value[$index]) ? [$value[$index]] : [];
        }

        // start and end index
        if (preg_match('/^-?\d+(?:\s*,\s*-?\d+)*$/', $expr)) {
            if (!array_is_list($value)) {
                return [];
            }

            $result = [];
            foreach (explode(',', $expr) as $index) {
                $index = (int) trim($index);
                if ($index < 0) {
                    $index = \count($value) + $index;
                }
                if (isset($value[$index])) {
                    $result[] = $value[$index];
                }
            }

            return $result;
        }

        // start, end and step
        if (preg_match('/^(-?\d*):(-?\d*)(?::(-?\d+))?$/', $expr, $matches)) {
            if (!array_is_list($value)) {
                return [];
            }

            $length = \count($value);
            $start = '' !== $matches[1] ? (int) $matches[1] : null;
            $end = '' !== $matches[2] ? (int) $matches[2] : null;
            $step = isset($matches[3]) && '' !== $matches[3] ? (int) $matches[3] : 1;

            if (0 === $step || $start > $length) {
                return [];
            }

            if (null === $start) {
                $start = $step > 0 ? 0 : $length - 1;
            } else {
                if ($start < 0) {
                    $start = $length + $start;
                }
                $start = max(0, min($start, $length - 1));
            }

            if (null === $end) {
                $end = $step > 0 ? $length : -1;
            } else {
                if ($end < 0) {
                    $end = $length + $end;
                }
                if ($step > 0) {
                    $end = max(0, min($end, $length));
                } else {
                    $end = max(-1, min($end, $length - 1));
                }
            }

            $result = [];
            for ($i = $start; $step > 0 ? $i < $end : $i > $end; $i += $step) {
                if (isset($value[$i])) {
                    $result[] = $value[$i];
                }
            }

            return $result;
        }

        // filter expressions
        if (preg_match('/^\?(.*)$/', $expr, $matches)) {
            $filterExpr = $matches[1];

            if (preg_match('/^(\w+)\s*\([^()]*\)\s*([<>=!]+.*)?$/', $filterExpr)) {
                $filterExpr = "($filterExpr)";
            }

            if (!str_starts_with($filterExpr, '(')) {
                throw new JsonCrawlerException($expr, 'Invalid filter expression');
            }

            // remove outrer filter parentheses
            $innerExpr = substr(substr($filterExpr, 1), 0, -1);

            return $this->evaluateFilter($innerExpr, $value);
        }

        // quoted strings for object keys
        if (preg_match('/^([\'"])(.*)\1$/', $expr, $matches)) {
            $key = stripslashes($matches[2]);

            return \array_key_exists($key, $value) ? [$value[$key]] : [];
        }

        throw new \LogicException(\sprintf('Unsupported bracket expression "%s".', $expr));
    }

    private function evaluateFilter(string $expr, mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (!\is_array($item)) {
                continue;
            }

            if ($this->evaluateFilterExpression($expr, $item)) {
                $result[] = $item;
            }
        }

        return $result;
    }

    private function evaluateFilterExpression(string $expr, array $context): bool
    {
        $expr = trim($expr);

        if (str_contains($expr, '&&')) {
            $parts = array_map('trim', explode('&&', $expr));
            foreach ($parts as $part) {
                if (!$this->evaluateFilterExpression($part, $context)) {
                    return false;
                }
            }

            return true;
        }

        if (str_contains($expr, '||')) {
            $parts = array_map('trim', explode('||', $expr));
            $result = false;
            foreach ($parts as $part) {
                $result = $result || $this->evaluateFilterExpression($part, $context);
            }

            return $result;
        }

        $operators = ['!=', '==', '>=', '<=', '>', '<'];
        foreach ($operators as $op) {
            if (str_contains($expr, $op)) {
                [$left, $right] = array_map('trim', explode($op, $expr, 2));
                $leftValue = $this->evaluateScalar($left, $context);
                $rightValue = $this->evaluateScalar($right, $context);

                return $this->compare($leftValue, $rightValue, $op);
            }
        }

        if (str_starts_with($expr, '@.')) {
            $path = substr($expr, 2);

            return \array_key_exists($path, $context);
        }

        // function calls
        if (preg_match('/^(\w+)\((.*)\)$/', $expr, $matches)) {
            $functionName = $matches[1];
            if (!isset(self::RFC9535_FUNCTIONS[$functionName])) {
                throw new JsonCrawlerException($expr, \sprintf('invalid function "%s"', $functionName));
            }

            $functionResult = $this->evaluateFunction($functionName, $matches[2], $context);

            return is_numeric($functionResult) ? $functionResult > 0 : (bool) $functionResult;
        }

        return false;
    }

    private function evaluateScalar(string $expr, array $context): mixed
    {
        if (is_numeric($expr)) {
            return str_contains($expr, '.') ? (float) $expr : (int) $expr;
        }

        if ('true' === $expr) {
            return true;
        }

        if ('false' === $expr) {
            return false;
        }

        if ('null' === $expr) {
            return null;
        }

        // string literals
        if (preg_match('/^([\'"])(.*)\1$/', $expr, $matches)) {
            return $matches[2];
        }

        // current node references
        if (str_starts_with($expr, '@.')) {
            $path = substr($expr, 2);

            return $context[$path] ?? null;
        }

        // function calls
        if (preg_match('/^(\w+)\((.*)\)$/', $expr, $matches)) {
            $functionName = $matches[1];
            if (!isset(self::RFC9535_FUNCTIONS[$functionName])) {
                throw new JsonCrawlerException($expr, \sprintf('invalid function "%s"', $functionName));
            }

            return $this->evaluateFunction($functionName, $matches[2], $context);
        }

        return null;
    }

    private function evaluateFunction(string $name, string $args, array $context): mixed
    {
        $args = array_map(
            fn ($arg) => $this->evaluateScalar(trim($arg), $context),
            explode(',', $args)
        );

        $value = $args[0] ?? null;

        return match ($name) {
            'length' => match (true) {
                \is_string($value) => mb_strlen($value),
                \is_array($value) => \count($value),
                default => 0,
            },
            'count' => \is_array($value) ? \count($value) : 0,
            'match' => match (true) {
                \is_string($value) && \is_string($args[1] ?? null) => (bool) @preg_match(\sprintf('/^%s$/', $args[1]), $value),
                default => false,
            },
            'search' => match (true) {
                \is_string($value) && \is_string($args[1] ?? null) => (bool) @preg_match("/$args[1]/", $value),
                default => false,
            },
            'value' => $value,
            default => null,
        };
    }

    private function evaluateRecursive(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $result = [$value];
        foreach ($value as $item) {
            if (\is_array($item)) {
                $result = array_merge($result, $this->evaluateRecursive($item));
            }
        }

        return $result;
    }

    private function compare(mixed $left, mixed $right, string $operator): bool
    {
        return match ($operator) {
            '==' => $left === $right,
            '!=' => $left !== $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            '<' => $left < $right,
            '<=' => $left <= $right,
            default => false,
        };
    }
}
