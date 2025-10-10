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
use Symfony\Component\JsonPath\Exception\InvalidJsonPathException;
use Symfony\Component\JsonPath\Exception\InvalidJsonStringInputException;
use Symfony\Component\JsonPath\Exception\JsonCrawlerException;
use Symfony\Component\JsonPath\Tokenizer\JsonPathToken;
use Symfony\Component\JsonPath\Tokenizer\JsonPathTokenizer;
use Symfony\Component\JsonPath\Tokenizer\TokenType;
use Symfony\Component\JsonStreamer\Exception\UnexpectedValueException;
use Symfony\Component\JsonStreamer\Read\Splitter;

/**
 * Crawls a JSON document using a JSON Path as described in the RFC 9535.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9535
 *
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
final class JsonCrawler implements JsonCrawlerInterface
{
    private static \stdClass $nothing;

    private const RFC9535_FUNCTIONS = [
        'length' => true,
        'count' => true,
        'match' => true,
        'search' => true,
        'value' => true,
    ];

    /**
     * Comparison operators and their corresponding lengths.
     */
    private const COMPARISON_OPERATORS = [
        '!=' => 2,
        '==' => 2,
        '>=' => 2,
        '<=' => 2,
        '>' => 1,
        '<' => 1,
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
            if ($this->isComplexBracketExpression($query)) {
                preg_match('/^\$\[([^\[\]]+)]$/', $query, $matches);

                if (\is_resource($json = $this->raw)) {
                    if (0 !== ftell($this->raw)) {
                        rewind($this->raw);
                    }

                    if (false === $json = stream_get_contents($this->raw)) {
                        throw new \RuntimeException('Failed to read from resource stream.');
                    }
                }

                try {
                    $data = json_decode($json, false, 512, \JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new InvalidJsonStringInputException($e->getMessage(), $e);
                }

                return $this->normalizeStorage($this->evaluateBracket($matches[1], $data));
            }

            $tokens = JsonPathTokenizer::tokenize($query);

            if (\is_resource($json = $this->raw)) {
                if (!class_exists(Splitter::class)) {
                    throw new \LogicException('The JsonStreamer package is required to evaluate a path against a resource. Try running "composer require symfony/json-streamer".');
                }

                try {
                    $simplified = JsonPathUtils::findSmallestDeserializableStringAndPath($tokens, $this->raw);

                    $tokens = $simplified['tokens'];
                    $json = $simplified['json'];

                    if (!$json) {
                        throw new \LogicException(); // fallback to reading the entire stream
                    }
                } catch (\LogicException|UnexpectedValueException) {
                    if (0 !== ftell($this->raw)) {
                        rewind($this->raw);
                    }

                    if (false === $json = stream_get_contents($this->raw)) {
                        throw new \RuntimeException('Failed to read from resource stream.');
                    }

                    $tokens = JsonPathTokenizer::tokenize($query);
                }
            }

            try {
                $data = json_decode($json, false, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new InvalidJsonStringInputException($e->getMessage(), $e);
            }

            return $this->normalizeStorage($this->evaluateTokensOnDecodedData($tokens, $data));
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (InvalidJsonPathException $e) {
            throw new JsonCrawlerException($query, $e->getMessage(), previous: $e);
        }
    }

    private function isComplexBracketExpression(JsonPath $query): bool
    {
        if (!preg_match('/^\$\[([^\[\]]+)]$/', (string) $query, $matches)) {
            return false;
        }

        $bracketContent = $matches[1];
        if (!str_contains($bracketContent, ',') || !str_contains($bracketContent, '?')) {
            return false;
        }

        return $this->isValidMixedBracketExpression($bracketContent);
    }

    private function evaluateTokensOnDecodedData(array $tokens, mixed $data): array
    {
        $current = [$data];
        $tokenCount = \count($tokens);

        for ($i = 0; $i < $tokenCount; ++$i) {
            $token = $tokens[$i];
            $next = [];

            // recursive token followed by bracket with property selectors
            if (TokenType::Recursive === $token->type
                && isset($tokens[$i + 1])
                && TokenType::Bracket === $tokens[$i + 1]->type
                && (str_contains($tokens[$i + 1]->value, '"') || str_contains($tokens[$i + 1]->value, "'"))
            ) {
                $bracketToken = $tokens[$i + 1];

                foreach ($current as $value) {
                    $recursiveResults = $this->evaluateToken($token, $value);

                    foreach ($recursiveResults as $recursiveValue) {
                        if (\is_array($recursiveValue) && !array_is_list($recursiveValue) || $recursiveValue instanceof \stdClass) {
                            $bracketResults = $this->evaluateToken($bracketToken, $recursiveValue);
                            $next = array_merge($next, $bracketResults);
                        }
                    }
                }

                ++$i;
            } else {
                foreach ($current as $value) {
                    $next = array_merge($next, $this->evaluateToken($token, $value));
                }
            }

            $current = $next;
        }

        return $current;
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
        if (!$this->isArrayOrObject($value)) {
            return [];
        }

        if ('*' === $name) {
            return array_values((array) $value);
        }

        return $this->getValueIfKeyExists($value, $name);
    }

    private function evaluateBracket(string $expr, mixed $value): array
    {
        if (!$this->isArrayOrObject($value)) {
            return [];
        }

        if (str_contains($expr, ',') && (str_starts_with($trimmed = trim($expr), ',') || str_ends_with($trimmed, ','))) {
            throw new JsonCrawlerException($expr, 'Expression cannot have leading or trailing commas');
        }

        if ('*' === $expr = JsonPathUtils::normalizeWhitespace($expr)) {
            return array_values((array) $value);
        }

        // single negative index
        if (preg_match('/^-\d+$/', $expr)) {
            if (JsonPathUtils::hasLeadingZero($expr) || JsonPathUtils::isIntegerOverflow($expr) || '-0' === $expr) {
                throw new JsonCrawlerException($expr, 'invalid index selector');
            }

            // numeric indices only work on lists
            if (!\is_array($value)) {
                return [];
            }

            $index = \count($value) + (int) $expr;

            return isset($value[$index]) ? [$value[$index]] : [];
        }

        // single positive index
        if (preg_match('/^\d+$/', $expr)) {
            if (JsonPathUtils::hasLeadingZero($expr) || JsonPathUtils::isIntegerOverflow($expr)) {
                throw new JsonCrawlerException($expr, 'invalid index selector');
            }

            // numeric indices only work on lists
            if (!\is_array($value)) {
                return [];
            }

            $index = (int) $expr;

            return isset($value[$index]) ? [$value[$index]] : [];
        }

        // start and end index
        if (preg_match('/^-?\d+(?:\s*,\s*-?\d+)*$/', $expr)) {
            foreach (explode(',', $expr) as $exprPart) {
                if (JsonPathUtils::hasLeadingZero($exprPart = trim($exprPart)) || JsonPathUtils::isIntegerOverflow($exprPart) || '-0' === $exprPart) {
                    throw new JsonCrawlerException($expr, 'invalid index selector');
                }
            }

            // numeric indices only work on lists
            if (!\is_array($value)) {
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

        if (preg_match('/^(-?\d*+)\s*+:\s*+(-?\d*+)(?:\s*+:\s*+(-?\d*+))?$/', $expr, $matches)) {
            if (!\is_array($value) || !array_is_list($value)) {
                return [];
            }

            $startStr = trim($matches[1]);
            $endStr = trim($matches[2]);
            $stepStr = trim($matches[3] ?? '1');

            if (
                JsonPathUtils::hasLeadingZero($startStr)
                || JsonPathUtils::hasLeadingZero($endStr)
                || JsonPathUtils::hasLeadingZero($stepStr)
            ) {
                throw new JsonCrawlerException($expr, 'slice selector numbers cannot have leading zeros');
            }

            if ('-0' === $startStr || '-0' === $endStr || '-0' === $stepStr) {
                throw new JsonCrawlerException($expr, 'slice selector cannot contain negative zero');
            }

            if (
                JsonPathUtils::isIntegerOverflow($startStr)
                || JsonPathUtils::isIntegerOverflow($endStr)
                || JsonPathUtils::isIntegerOverflow($stepStr)
            ) {
                throw new JsonCrawlerException($expr, 'slice selector integer overflow');
            }

            $length = \count($value);
            $start = '' !== $startStr ? (int) $startStr : null;
            $end = '' !== $endStr ? (int) $endStr : null;
            $step = '' !== $stepStr ? (int) $stepStr : 1;

            if (0 === $step) {
                return [];
            }

            if (null === $start) {
                $start = $step > 0 ? 0 : $length - 1;
            } else {
                if ($start < 0) {
                    $start = $length + $start;
                }

                if (0 < $step && $start >= $length) {
                    return [];
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

        // comma-separated expressions with at least one filter (e.g. "?@.a,?@.b", "?@.a,1", "1,?@.a=='b',1:")
        if (str_contains($expr, ',') && str_contains($expr, '?') && $this->isValidMixedBracketExpression($expr)) {
            $parts = JsonPathUtils::parseCommaSeparatedValues($expr);
            $result = [];
            foreach ($parts as $part) {
                $part = trim($part);

                if (preg_match('/^\?(.*)$/', $part, $matches)) {
                    $result = array_merge($result, $this->evaluateFilter(trim($matches[1]), $value));

                    continue;
                }

                $selectorResult = $this->evaluateBracket($part, $value);
                $result = array_merge($result, $selectorResult);
            }

            return $result;
        }

        // filter expressions
        if (preg_match('/^\?(.*)$/', $expr, $matches)) {
            $filterExpr = trim($matches[1]);

            // is it a function call?
            if (preg_match('/^(\w+)\s*\([^()]*\)\s*([<>=!]+.*)?$/', $filterExpr)) {
                $filterExpr = "($filterExpr)";
            }

            $needsParentheses = true;
            if (str_starts_with($filterExpr, '(') && str_ends_with($filterExpr, ')')) {
                $depth = 0;
                $isWrapped = true;
                $filterLen = \strlen($filterExpr);

                for ($i = 0; $i < $filterLen; ++$i) {
                    $char = $filterExpr[$i];
                    if ('(' === $char) {
                        ++$depth;
                    } elseif (')' === $char && 0 === --$depth && $i < $filterLen - 1) {
                        $isWrapped = false;
                        break;
                    }
                }

                if ($isWrapped) {
                    $needsParentheses = false;
                    $filterExpr = trim(substr($filterExpr, 1, -1));
                }
            }

            if ($needsParentheses && !str_starts_with($filterExpr, '(')) {
                $filterExpr = "($filterExpr)";
            }

            $this->validateFilterExpression($filterExpr);

            return $this->evaluateFilter($filterExpr, $value);
        }

        // comma-separated values, e.g. `['key1', 'key2', 123]` or `[0, 1, 'key']`
        if (str_contains($expr, ',')) {
            $parts = JsonPathUtils::parseCommaSeparatedValues($expr);

            $result = [];

            $allStringKeys = true;
            foreach ($parts as $part) {
                $part = trim($part);
                if (!preg_match('/^([\'"])(.*)\1$/', $part)) {
                    $allStringKeys = false;

                    break;
                }
            }

            if ($allStringKeys) {
                if (!\is_array($value) || !array_is_list($value)) {
                    foreach ($parts as $part) {
                        $part = trim($part);

                        if (!preg_match('/^([\'"])(.*)\1$/', $part, $matches)) {
                            continue;
                        }

                        $key = JsonPathUtils::unescapeString($matches[2], $matches[1]);
                        $result = array_merge($result, $this->getValueIfKeyExists($value, $key));
                    }

                    return $result;
                }

                foreach ($value as $item) {
                    if (!\is_array($item)) {
                        continue;
                    }

                    foreach ($parts as $part) {
                        $part = trim($part);
                        if (!preg_match('/^([\'"])(.*)\1$/', $part, $matches)) {
                            continue;
                        }

                        $key = JsonPathUtils::unescapeString($matches[2], $matches[1]);
                        $result = array_merge($result, $this->getValueIfKeyExists($item, $key));
                    }
                }

                return $result;
            }

            foreach ($parts as $part) {
                $part = trim($part);

                if ('*' === $part) {
                    $result = array_merge($result, array_values((array) $value));
                } elseif (preg_match('/^(-?\d*+)\s*+:\s*+(-?\d*+)(?:\s*+:\s*+(-?\d++))?$/', $part, $matches)) {
                    // slice notation
                    $sliceResult = $this->evaluateBracket($part, $value);
                    $result = array_merge($result, $sliceResult);
                } elseif (preg_match('/^([\'"])(.*)\1$/', $part, $matches)) {
                    $key = JsonPathUtils::unescapeString($matches[2], $matches[1]);

                    if (\is_array($value) && array_is_list($value)) {
                        // for arrays, find ALL objects that contain this key
                        foreach ($value as $item) {
                            if ($this->getValueIfKeyExists($item, $key)) {
                                $result[] = $item;
                            }
                        }
                    } else {
                        $result = array_merge($result, $this->getValueIfKeyExists($value, $key));
                    }
                } elseif (preg_match('/^-?\d+$/', $part)) {
                    // numeric index
                    $index = (int) $part;
                    if ($index < 0) {
                        $index = \count($value) + $index;
                    }

                    if (\is_array($value) && array_is_list($value) && \array_key_exists($index, $value)) {
                        $result[] = $value[$index];
                    }
                }
            }

            return $result;
        }

        if (preg_match('/^([\'"])(.*)\1$/', $expr, $matches)) {
            $key = JsonPathUtils::unescapeString($matches[2], $matches[1]);

            if (\is_array($value)) {
                return [];
            }

            return $this->getValueIfKeyExists($value, $key);
        }

        throw new InvalidJsonPathException(\sprintf('Unsupported bracket expression "%s".', $expr));
    }

    private function evaluateFilter(string $expr, mixed $value): array
    {
        if (!$this->isArrayOrObject($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if ($this->evaluateFilterExpression($expr, $item)) {
                $result[] = $item;
            }
        }

        return $result;
    }

    private function evaluateFilterExpression(string $expr, mixed $context): bool
    {
        $expr = JsonPathUtils::normalizeWhitespace($expr);

        // remove outer parentheses if they wrap the entire expression
        if (str_starts_with($expr, '(') && str_ends_with($expr, ')')) {
            $depth = 0;
            $isWrapped = true;
            $i = -1;
            while (null !== $char = $expr[++$i] ?? null) {
                if ('(' === $char) {
                    ++$depth;
                } elseif (')' === $char && 0 === --$depth && isset($expr[$i + 1])) {
                    $isWrapped = false;
                    break;
                }
            }
            if ($isWrapped) {
                $expr = trim(substr($expr, 1, -1));
            }
        }

        if (str_starts_with($expr, '!')) {
            return !$this->evaluateFilterExpression(trim(substr($expr, 1)), $context);
        }

        if ($logicalOp = $this->findRightmostLogicalOperator($expr)) {
            $left = trim(substr($expr, 0, $logicalOp['position']));
            $right = trim(substr($expr, $logicalOp['position'] + \strlen($logicalOp['operator'])));

            if ('||' === $logicalOp['operator']) {
                return $this->evaluateFilterExpression($left, $context) || $this->evaluateFilterExpression($right, $context);
            }

            return $this->evaluateFilterExpression($left, $context) && $this->evaluateFilterExpression($right, $context);
        }

        foreach (self::COMPARISON_OPERATORS as $op => $len) {
            if (str_contains($expr, $op)) {
                if (false === $opPos = $this->findOperatorPosition($expr, $op)) {
                    continue;
                }

                $leftValue = $this->evaluateScalar(trim(substr($expr, 0, $opPos)), $context);
                $rightValue = $this->evaluateScalar(trim(substr($expr, $opPos + $len)), $context);

                return $this->compare($leftValue, $rightValue, $op);
            }
        }

        if ('@' === $expr || '$' === $expr) {
            return true;
        }

        if (str_starts_with($expr, '$')) {
            try {
                return (bool) $this->evaluate(new JsonPath($expr));
            } catch (JsonCrawlerException) {
                return false;
            }
        }

        if (str_starts_with($expr, '@.')) {
            return $this->isArrayOrObject($context) && $this->evaluateTokensOnDecodedData(JsonPathTokenizer::tokenize(new JsonPath('$'.substr($expr, 1))), $context);
        }

        if (str_starts_with($expr, '@[') && str_ends_with($expr, ']')) {
            return $this->isArrayOrObject($context) && $this->evaluateBracket(substr($expr, 2, -1), $context);
        }

        // function calls
        if (preg_match('/^(\w++)\s*+\((.*)\)$/', $expr, $matches)) {
            $functionName = trim($matches[1]);
            if (!isset(self::RFC9535_FUNCTIONS[$functionName])) {
                throw new JsonCrawlerException($expr, \sprintf('invalid function "%s"', $functionName));
            }

            $functionResult = $this->evaluateFunction($functionName, $matches[2], $context);

            return is_numeric($functionResult) ? $functionResult > 0 : (bool) $functionResult;
        }

        return false;
    }

    private function findRightmostLogicalOperator(string $expr): ?array
    {
        $rightmostPos = -1;
        $rightmostOp = null;
        $depth = 0;
        $exprLen = \strlen($expr);

        for ($i = 0; $i < $exprLen; ++$i) {
            $char = $expr[$i];

            if ('(' === $char) {
                ++$depth;
            } elseif (')' === $char) {
                --$depth;
            } elseif (0 === $depth && '||' === substr($expr, $i, 2)) {
                $rightmostPos = $i;
                $rightmostOp = '||';
                ++$i;
            }
        }

        if (!$rightmostOp) {
            $depth = 0;
            for ($i = 0; $i < $exprLen; ++$i) {
                $char = $expr[$i];

                if ('(' === $char) {
                    ++$depth;
                } elseif (')' === $char) {
                    --$depth;
                } elseif (0 === $depth && '&&' === substr($expr, $i, 2)) {
                    $rightmostPos = $i;
                    $rightmostOp = '&&';
                    ++$i;
                }
            }
        }

        return $rightmostOp ? ['operator' => $rightmostOp, 'position' => $rightmostPos] : null;
    }

    private function evaluateScalar(string $expr, mixed $context): mixed
    {
        $expr = JsonPathUtils::normalizeWhitespace($expr);

        if (JsonPathUtils::isJsonNumber($expr)) {
            return str_contains($expr, '.') || str_contains(strtolower($expr), 'e') ? (float) $expr : (int) $expr;
        }

        // only validate tokens that look like standalone numbers
        if (preg_match('/^[\d+\-.eE]+$/', $expr) && preg_match('/\d/', $expr)) {
            throw new JsonCrawlerException($expr, \sprintf('Invalid number format "%s"', $expr));
        }

        if ('@' === $expr) {
            return $context;
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
            return JsonPathUtils::unescapeString($matches[2], $matches[1]);
        }

        // absolute path references
        if (str_starts_with($expr, '$')) {
            if ($this->isNonSingularQuery($expr)) {
                throw new JsonCrawlerException($expr, 'non-singular query is not comparable');
            }

            return $this->evaluate(new JsonPath($expr))[0] ?? null;
        }

        // current node references
        if (str_starts_with($expr, '@')) {
            if (!$this->isArrayOrObject($context)) {
                return null;
            }

            $path = substr($expr, 1);

            if (str_starts_with($path, '[') && str_ends_with($path, ']')) {
                $bracketContent = substr($path, 1, -1);
                $result = $this->evaluateBracket($bracketContent, $context);

                return $result ? $result[0] : Nothing::Nothing;
            }

            $results = $this->evaluateTokensOnDecodedData(JsonPathTokenizer::tokenize(new JsonPath('$'.$path)), $context);

            return $results ? $results[0] : Nothing::Nothing;
        }

        // function calls
        if (preg_match('/^(\w++)\((.*)\)$/', $expr, $matches)) {
            if (!isset(self::RFC9535_FUNCTIONS[$functionName = trim($matches[1])])) {
                throw new JsonCrawlerException($expr, \sprintf('invalid function "%s"', $functionName));
            }

            return $this->evaluateFunction($functionName, $matches[2], $context);
        }

        return null;
    }

    private function evaluateFunction(string $name, string $args, mixed $context): mixed
    {
        $argList = [];
        $nodelistSizes = [];
        if ($args = trim($args)) {
            $args = JsonPathUtils::parseCommaSeparatedValues($args);
            foreach ($args as $arg) {
                $arg = trim($arg);
                if (str_starts_with($arg, '$')) { // special handling for absolute paths
                    $results = $this->evaluate(new JsonPath($arg));
                    $argList[] = $results[0] ?? null;
                    $nodelistSizes[] = \count($results);
                } elseif (!str_starts_with($arg, '@')) { // special handling for @ to track nodelist size
                    $argList[] = $this->evaluateScalar($arg, $context);
                    $nodelistSizes[] = 1;
                } elseif ('@' === $arg) {
                    $argList[] = $context;
                    $nodelistSizes[] = 1;
                } elseif (!$this->isArrayOrObject($context)) {
                    $argList[] = null;
                    $nodelistSizes[] = 0;
                } elseif (str_starts_with($pathPart = substr($arg, 1), '[')) {
                    // handle bracket expressions like @['a','d']
                    $results = $this->evaluateBracket(substr($pathPart, 1, -1), $context);
                    $argList[] = $results;
                    $nodelistSizes[] = \count($results);
                } else {
                    // handle dot notation like @.a
                    $results = $this->evaluateTokensOnDecodedData(JsonPathTokenizer::tokenize(new JsonPath('$'.$pathPart)), $context);
                    $argList[] = $results[0] ?? null;
                    $nodelistSizes[] = \count($results);
                }
            }
        }

        $value = $argList[0] ?? null;
        $nodelistSize = $nodelistSizes[0] ?? 0;

        return match ($name) {
            'length' => match (true) {
                \is_string($value) => mb_strlen($value),
                \is_array($value) => \count($value),
                $value instanceof \stdClass => \count(get_object_vars($value)),
                default => Nothing::Nothing,
            },
            'count' => $nodelistSize,
            'match' => match (true) {
                \is_string($value) && \is_string($argList[1] ?? null) => (bool) @preg_match(\sprintf('/^%s$/u', $this->transformJsonPathRegex($argList[1])), $value),
                default => false,
            },
            'search' => match (true) {
                \is_string($value) && \is_string($argList[1] ?? null) => (bool) @preg_match("/{$this->transformJsonPathRegex($argList[1])}/u", $value),
                default => false,
            },
            'value' => 1 < $nodelistSize ? Nothing::Nothing : (1 === $nodelistSize ? (\is_array($value) ? ($value[0] ?? null) : $value) : $value),
            default => null,
        };
    }

    private function evaluateRecursive(mixed $value): array
    {
        if (!$this->isArrayOrObject($value)) {
            return [];
        }

        $result = [];

        $result[] = $value;

        foreach ($value as $item) {
            if ($this->isArrayOrObject($item)) {
                $childResults = $this->evaluateRecursive($item);
                $result = array_merge($result, $childResults);
            }
        }

        return $result;
    }

    private function compare(mixed $left, mixed $right, string $operator): bool
    {
        return match ($operator) {
            '==' => $this->compareEquality($left, $right),
            '!=' => !$this->compareEquality($left, $right),
            '>', '>=', '<', '<=' => $this->compareOrdering($left, $right, $operator),
            default => false,
        };
    }

    private function compareEquality(mixed $left, mixed $right): bool
    {
        $leftIsNothing = $left === Nothing::Nothing;
        $rightIsNothing = $right === Nothing::Nothing;

        if (
            $leftIsNothing && $rightIsNothing
            || ($leftIsNothing && 0 === $right || 0 === $left && $rightIsNothing)
        ) {
            return true;
        }

        if ($leftIsNothing || $rightIsNothing) {
            return false;
        }

        if ((\is_int($left) || \is_float($left)) && (\is_int($right) || \is_float($right))) {
            return $left == $right;
        }

        if (\is_string($left) && \is_string($right) || \is_bool($left) && \is_bool($right)) {
            return $left === $right;
        }

        if (null === $left && null === $right) {
            return true;
        }

        // arrays must have equal length and equal corresponding elements
        if (\is_array($left) && \is_array($right)) {
            return $this->compareArraysDeep($left, $right);
        }

        // objects must have identical names and equal corresponding values
        if ($left instanceof \stdClass && $right instanceof \stdClass) {
            return $this->compareObjectsDeep($left, $right);
        }

        // null (missing property) equals 0 when compared to function results
        if (null === $left && 0 === $right || 0 === $left && null === $right) {
            return true;
        }

        // different types are not equal
        return false;
    }

    private function compareArraysDeep(array $left, array $right): bool
    {
        $leftIsList = array_is_list($left);
        $rightIsList = array_is_list($right);
        $leftCount = \count($left);

        if ($leftIsList !== $rightIsList || $leftCount !== \count($right)) {
            return false;
        }

        foreach ($left as $key => $value) {
            if (!\array_key_exists($key, $right) || !$this->compareEquality($value, $right[$key])) {
                return false;
            }
        }

        return true;
    }

    private function compareObjectsDeep(\stdClass $left, \stdClass $right): bool
    {
        $leftVars = get_object_vars($left);
        $rightVars = get_object_vars($right);

        if (\count($leftVars) !== \count($rightVars)) {
            return false;
        }

        foreach ($leftVars as $key => $value) {
            if (!property_exists($right, $key) || !$this->compareEquality($value, $rightVars[$key])) {
                return false;
            }
        }

        return true;
    }

    private function compareOrdering(mixed $left, mixed $right, string $operator): bool
    {
        if (null === $left || null === $right) {
            return match ($operator) {
                '>=', '<=' => $left === $right,
                default => false,
            };
        }

        if ((\is_int($left) || \is_float($left)) && (\is_int($right) || \is_float($right)) || \is_bool($left) && \is_bool($right)) {
            $comparison = $left - $right;
        } elseif (\is_string($left) && \is_string($right)) {
            $comparison = strcmp($left, $right);
        } else {
            return false;
        }

        return match ($operator) {
            '>' => $comparison > 0,
            '>=' => $comparison >= 0,
            '<' => $comparison < 0,
            '<=' => $comparison <= 0,
            default => false,
        };
    }

    private function isNonSingularQuery(string $expr): bool
    {
        try {
            $tokens = JsonPathTokenizer::tokenize(new JsonPath($expr));

            foreach ($tokens as $token) {
                if (TokenType::Bracket === $token->type) {
                    $trimmedValue = trim($token->value);

                    if (
                        str_contains($token->value, ',')
                        || '*' === $trimmedValue
                        || preg_match('/^(-?\d*+)\s*+:\s*+(-?\d*+)(?:\s*+:\s*+(-?\d*+))?$/', $trimmedValue)
                    ) {
                        return true;
                    }
                }

                if (TokenType::Name === $token->type && '*' === $token->value || TokenType::Recursive === $token->type) {
                    return true;
                }
            }

            return false;
        } catch (InvalidJsonPathException) {
            return true;
        }
    }

    private function isNonSingularRelativeQuery(string $expr): bool
    {
        return preg_match('/@\[.*,.*]/', $expr) || '@.*' === $expr || preg_match('/@\[.*:.*]/', $expr);
    }

    private function findOperatorPosition(string $expr, string $op): int|false
    {
        $bracketDepth = 0;
        $parenthesisDepth = 0;
        $length = \strlen($expr);
        $opLength = \strlen($op);

        for ($i = 0; $i <= $length - $opLength; ++$i) {
            $char = $expr[$i];

            if ('[' === $char) {
                ++$bracketDepth;
            } elseif (']' === $char) {
                --$bracketDepth;
            } elseif ('(' === $char) {
                ++$parenthesisDepth;
            } elseif (')' === $char) {
                --$parenthesisDepth;
            } elseif (!$bracketDepth && !$parenthesisDepth && substr($expr, $i, $opLength) === $op) {
                return $i;
            }
        }

        return false;
    }

    private function validateFilterExpression(string $expr): void
    {
        if ($logicalOp = $this->findRightmostLogicalOperator($expr)) {
            $this->validateFilterExpression(trim(substr($expr, 0, $logicalOp['position']))); // left
            $this->validateFilterExpression(trim(substr($expr, $logicalOp['position'] + \strlen($logicalOp['operator'])))); // right

            return;
        }

        foreach (self::COMPARISON_OPERATORS as $op => $len) {
            if (str_contains($expr, $op)) {
                if (false === $opPos = $this->findOperatorPosition($expr, $op)) {
                    continue;
                }

                $left = trim(substr($expr, 0, $opPos));
                $right = trim(substr($expr, $opPos + $len));

                if (
                    str_starts_with($left, '$') && $this->isNonSingularQuery($left)
                    || str_starts_with($right, '$') && $this->isNonSingularQuery($right)
                ) {
                    throw new JsonCrawlerException($left, 'non-singular query is not comparable');
                }

                if (
                    str_starts_with($left, '@') && $this->isNonSingularRelativeQuery($left)
                    || str_starts_with($right, '@') && $this->isNonSingularRelativeQuery($right)
                ) {
                    throw new JsonCrawlerException($left, 'non-singular query is not comparable');
                }

                return;
            }
        }
    }

    /**
     * Transforms JSONPath regex patterns to comply with RFC 9485.
     *
     * @see https://www.rfc-editor.org/rfc/rfc9485.html#name-pcre-re2-and-ruby-regexps
     */
    private function transformJsonPathRegex(string $pattern): string
    {
        $result = '';
        $inCharClass = false;
        $i = -1;

        while (null !== $char = $pattern[++$i] ?? null) {
            switch ($char) {
                case '\\': $char .= $pattern[++$i] ?? '';
                    break;
                case '[': $inCharClass = true;
                    break;
                case ']': $inCharClass = false;
                    break;
                case '.': $inCharClass || $char = '[^\r\n]';
                    break;
            }

            $result .= $char;
        }

        return $result;
    }

    private function isArrayOrObject(mixed $value): bool
    {
        return \is_array($value) || $value instanceof \stdClass;
    }

    private function normalizeStorage(\stdClass|array $data): array
    {
        return array_map(function ($value) {
            return $value instanceof \stdClass || $value && \is_array($value) ? self::normalizeStorage($value) : $value;
        }, (array) $data);
    }

    private function isValidMixedBracketExpression(string $expr): bool
    {
        $parts = JsonPathUtils::parseCommaSeparatedValues($expr);
        $hasFilter = false;
        $validMixed = true;

        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/^\?/', $part)) {
                $hasFilter = true;
                // complete filter expression and not part of a comparison?
                if (!preg_match('/^\?[^?]*$/', $part)) {
                    $validMixed = false;
                    break;
                }
            } elseif (!preg_match('/^(\*|-?\d+|-?\d*:-?\d*(?::-?\d+)?|[\'"].*[\'"])$/', $part)) { // is it a valid non-filter selector (index, wildcard, slice)?
                $validMixed = false;
                break;
            }
        }

        return $hasFilter && $validMixed && 1 < \count($parts);
    }

    private function getValueIfKeyExists(mixed $value, string $key): array
    {
        return $this->isArrayOrObject($value) && \array_key_exists($key, $arrayValue = (array) $value) ? [$arrayValue[$key]] : [];
    }
}
