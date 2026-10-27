<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Formatter;

use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Component\Translation\Exception\LogicException;

/**
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 */
class IntlFormatter implements IntlFormatterInterface
{
    private bool $hasMessageFormatter;
    private array $cache = [];

    public function formatIntl(string $message, string $locale, array $parameters = []): string
    {
        // MessageFormatter constructor throws an exception if the message is empty
        if ('' === $message) {
            return '';
        }

        if (!$formatter = $this->cache[$locale][$message] ?? null) {
            if (!$this->hasMessageFormatter ??= class_exists(\MessageFormatter::class)) {
                throw new LogicException('Cannot parse message translation: please install the "intl" PHP extension or the "symfony/polyfill-intl-messageformatter" package.');
            }
            // without argument, MessageFormatter returns the message as is, unless a quote is doubled or precedes "}"
            if (false === strpbrk($message, "{'") || (!str_contains($message, '{') && !str_contains($message, "''") && !str_contains($message, "'}"))) {
                return $message;
            }
            try {
                $this->cache[$locale][$message] = $formatter = new \MessageFormatter($locale, $this->quoteMarkup($message));
            } catch (\IntlException $e) {
                throw new InvalidArgumentException(\sprintf('Invalid message format (error #%d): ', intl_get_error_code()).intl_get_error_message(), 0, $e);
            }
        }

        foreach ($parameters as $key => $value) {
            if (\in_array($key[0] ?? null, ['%', '{'], true)) {
                unset($parameters[$key]);
                $parameters[trim($key, '%{ }')] = $value;
            }
        }

        if (false === $message = $formatter->format($parameters)) {
            throw new InvalidArgumentException(\sprintf('Unable to format message (error #%s): ', $formatter->getErrorCode()).$formatter->getErrorMessage());
        }

        return $message;
    }

    /**
     * Quote the basic MF2 markup supported by HtmlTranslator so ICU preserves it as literal text for subsequent HTML rendering.
     */
    private function quoteMarkup(string $message): string
    {
        if (!str_contains($message, '{#') && !str_contains($message, '{/')) {
            return $message;
        }

        $pattern = $literal = '';
        $contexts = [''];
        $length = \strlen($message);

        for ($i = 0; $i < $length; ++$i) {
            $char = $message[$i];
            if ("'" === $char) {
                $next = $message[$i + 1] ?? '';
                if ("'" === $next) {
                    $literal .= "'";
                    ++$i;
                    continue;
                }

                $context = end($contexts);
                if ('{' === $next || '}' === $next || ('#' === $next && \in_array($context, ['plural', 'selectordinal'], true)) || ('|' === $next && 'choice' === $context)) {
                    preg_match("/\\G'((?:[^']|'')*)'?/", $message, $match, 0, $i);
                    $literal .= str_replace("''", "'", $match[1]);
                    $i += \strlen($match[0]) - 1;
                    continue;
                }

                $literal .= "'";
                continue;
            }

            if ('{' === $char && preg_match('/\G\{(?:\#[a-zA-Z][\w-]*\s*\/?|\/[a-zA-Z][\w-]*\s*)\}/', $message, $match, 0, $i)) {
                $literal .= $match[0];
                $i += \strlen($match[0]) - 1;
                continue;
            }

            if ('' !== $literal) {
                // Merge adjacent literal runs so their boundary does not become an ICU doubled apostrophe.
                $pattern .= false === strpbrk($literal, '{}#|') ? str_replace("'", "''", $literal) : "'".str_replace("'", "''", $literal)."'";
                $literal = '';
            }

            // Simple argument styles have their own apostrophe rules (e.g. date patterns).
            if ('{' === $char && preg_match("/\\G\\{\\s*[^\\s{},]+\\s*,\\s*(?:number|date|time|spellout|ordinal|duration)\\b(?:[^{}']+|'[^']*')*\\}/", $message, $match, 0, $i)) {
                $pattern .= $match[0];
                $i += \strlen($match[0]) - 1;
                continue;
            }

            if ('{' === $char) {
                $contexts[] = preg_match('/\G\{\s*[^\s{},]+\s*,\s*(plural|selectordinal|select|choice)\s*,/', $message, $match, 0, $i) ? $match[1] : end($contexts);
            } elseif ('}' === $char && 1 < \count($contexts)) {
                array_pop($contexts);
            }

            $pattern .= $char;
        }

        return $pattern.(false === strpbrk($literal, '{}#|') ? str_replace("'", "''", $literal) : "'".str_replace("'", "''", $literal)."'");
    }
}
