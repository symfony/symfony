<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Util;

/**
 * Convert from Symfony 3's plural syntax to Intl message format.
 * {@link https://messageformat.github.io/messageformat/page-guide}.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class IcuMessageConverter
{
    public static function convert(string $message, string $variableDelimiter = '%'): string
    {
        $array = self::getMessageArray($message);
        if (empty($array)) {
            return $message;
        }

        if (1 === \count($array) && isset($array[0])) {
            return self::replaceVariables($message, $variableDelimiter);
        }

        $icu = self::buildIcuString($array, $variableDelimiter);

        return $icu;
    }

    /**
     * Get an ICU like array.
     */
    private static function getMessageArray(string $message): array
    {
        if (preg_match('/^\|++$/', $message)) {
            // If the message only contains pipes ("|||")
            return array();
        } elseif (preg_match_all('/(?:\|\||[^\|])++/', $message, $matches)) {
            $parts = $matches[0];
        } else {
            throw new \LogicException(sprintf('Input string "%s" is not supported.', $message));
        }

        $intervalRegexp = <<<'EOF'
/^(?P<interval>
    ({\s*
        (\-?\d+(\.\d+)?[\s*,\s*\-?\d+(\.\d+)?]*)
    \s*})

        |

    (?P<left_delimiter>[\[\]])
        \s*
        (?P<left>-Inf|\-?\d+(\.\d+)?)
        \s*,\s*
        (?P<right>\+?Inf|\-?\d+(\.\d+)?)
        \s*
    (?P<right_delimiter>[\[\]])
)\s*(?P<message>.*?)$/xs
EOF;

        $standardRules = array();
        foreach ($parts as $part) {
            $part = trim(str_replace('||', '|', $part));

            // try to match an explicit rule, then fallback to the standard ones
            if (preg_match($intervalRegexp, $part, $matches)) {
                if ($matches[2]) {
                    foreach (explode(',', $matches[3]) as $n) {
                        $standardRules['='.$n] = $matches['message'];
                    }
                } else {
                    $leftNumber = '-Inf' === $matches['left'] ? -INF : (float) $matches['left'];
                    $rightNumber = \is_numeric($matches['right']) ? (float) $matches['right'] : INF;

                    $leftNumber = ('[' === $matches['left_delimiter'] ? $leftNumber : 1 + $leftNumber);
                    $rightNumber = (']' === $matches['right_delimiter'] ? 1 + $rightNumber : $rightNumber);

                    if ($leftNumber !== -INF && INF !== $rightNumber) {
                        for ($i = $leftNumber; $i < $rightNumber; ++$i) {
                            $standardRules['='.$i] = $matches['message'];
                        }
                    } else {
                        // $rightNumber is INF or $leftNumber is -INF
                        if (isset($standardRules['other'])) {
                            throw new \LogicException(sprintf('%s does not support converting messages with both "-Inf" and "Inf". Message: "%s"', __CLASS__, $message));
                        }
                        $standardRules['other'] = $matches['message'];
                    }
                }
            } elseif (preg_match('/^\w+\:\s*(.*?)$/', $part, $matches)) {
                $standardRules[] = $matches[1];
            } else {
                $standardRules[] = $part;
            }
        }

        return $standardRules;
    }

    private static function buildIcuString(array $data, string $variableDelimiter): string
    {
        $icu = "{ COUNT, plural,\n";
        foreach ($data as $key => $message) {
            $message = strtr($message, array('%count%' => '#'));
            $message = self::replaceVariables($message, $variableDelimiter);
            $icu .= sprintf("  %s {%s}\n", $key, $message);
        }
        $icu .= '}';

        return $icu;
    }

    private static function replaceVariables(string $message, string $variableDelimiter): string
    {
        $regex = sprintf('|%s(.*?)%s|s', $variableDelimiter, $variableDelimiter);

        return preg_replace($regex, '{$1}', $message);
    }
}
