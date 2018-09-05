<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

use Symfony\Component\Translation\Exception\InvalidArgumentException;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 4.2, use IdentityTranslator instead
 */
trait LegacyTranslatorTrait
{
    /**
     * Implementation of Symfony\Component\Translation\TranslationInterface::transChoice
     * {@inheritdoc}
     */
    public function transChoice($id, $number, array $parameters = array(), $domain = null, $locale = null)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.2, use the "trans()" method with intl formatted messages instead.', __METHOD__), E_USER_DEPRECATED);

        $id = (string) $id;
        $number = (float) $number;
        $locale = (string) $locale ?: $this->getLocale();

        $parts = array();
        if (preg_match('/^\|++$/', $id)) {
            $parts = explode('|', $id);
        } elseif (preg_match_all('/(?:\|\||[^\|])++/', $id, $matches)) {
            $parts = $matches[0];
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
                        if ($number == $n) {
                            return strtr($matches['message'], $parameters);
                        }
                    }
                } else {
                    $leftNumber = '-Inf' === $matches['left'] ? -INF : (float) $matches['left'];
                    $rightNumber = \is_numeric($matches['right']) ? (float) $matches['right'] : INF;

                    if (('[' === $matches['left_delimiter'] ? $number >= $leftNumber : $number > $leftNumber)
                        && (']' === $matches['right_delimiter'] ? $number <= $rightNumber : $number < $rightNumber)
                    ) {
                        return strtr($matches['message'], $parameters);
                    }
                }
            } elseif (preg_match('/^\w+\:\s*(.*?)$/', $part, $matches)) {
                $standardRules[] = $matches[1];
            } else {
                $standardRules[] = $part;
            }
        }

        $position = PluralizationRules::get($number, $locale, false);

        if (!isset($standardRules[$position])) {
            // when there's exactly one rule given, and that rule is a standard
            // rule, use this rule
            if (1 === \count($parts) && isset($standardRules[0])) {
                return strtr($standardRules[0], $parameters);
            }

            $message = sprintf('Unable to choose a translation for "%s" with locale "%s" for value "%d". Double check that this translation has the correct plural options (e.g. "There is one apple|There are %%count%% apples").', $id, $locale, $number);

            if (\class_exists(InvalidArgumentException::class)) {
                throw new InvalidArgumentException($message);
            }

            throw new \InvalidArgumentException($message);
        }

        return strtr($standardRules[$position], $parameters);
    }
}
