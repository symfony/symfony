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
use Symfony\Component\JsonPath\Tokenizer\JsonPathToken;
use Symfony\Component\JsonPath\Tokenizer\TokenType;
use Symfony\Component\JsonStreamer\Read\Splitter;

/**
 * Get the smallest deserializable JSON string from a list of tokens that doesn't need any processing.
 *
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @internal
 */
final class JsonPathUtils
{
    /**
     * @param JsonPathToken[] $tokens
     * @param resource        $json
     *
     * @return array{json: string, tokens: list<JsonPathToken>}
     */
    public static function findSmallestDeserializableStringAndPath(array $tokens, mixed $json): array
    {
        if (!\is_resource($json)) {
            throw new InvalidArgumentException('The JSON parameter must be a resource.');
        }

        $currentOffset = 0;
        $currentLength = null;

        $remainingTokens = $tokens;
        rewind($json);

        foreach ($tokens as $token) {
            $boundaries = [];

            if (TokenType::Name === $token->type) {
                foreach (Splitter::splitDict($json, $currentOffset, $currentLength) as $key => $bound) {
                    $boundaries[$key] = $bound;
                    if ($key === $token->value) {
                        break;
                    }
                }
            } elseif (TokenType::Bracket === $token->type && preg_match('/^\d+$/', $token->value)) {
                foreach (Splitter::splitList($json, $currentOffset, $currentLength) as $key => $bound) {
                    $boundaries[$key] = $bound;
                    if ($key === $token->value) {
                        break;
                    }
                }
            }

            if (!$boundaries) {
                // in case of a recursive descent or a filter, we can't reduce the JSON string
                break;
            }

            if (!\array_key_exists($token->value, $boundaries) || \count($remainingTokens) <= 1) {
                // the key given in the path is not found by the splitter or there is no remaining token to shift
                break;
            }

            // boundaries for the current token are found, we can remove it from the list
            // and substring the JSON string later
            $currentOffset = $boundaries[$token->value][0];
            $currentLength = $boundaries[$token->value][1];

            array_shift($remainingTokens);
        }

        return [
            'json' => stream_get_contents($json, $currentLength, $currentOffset ?: -1),
            'tokens' => $remainingTokens,
        ];
    }
}
