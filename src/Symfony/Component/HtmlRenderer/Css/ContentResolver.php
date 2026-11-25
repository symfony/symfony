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

/** @internal */
final class ContentResolver
{
    private const LANGUAGE_QUOTES = ['fr' => [['«', '»'], ['“', '”']], 'de' => [['„', '“'], ['‚', '‘']], 'es' => [['«', '»'], ['“', '”']], 'it' => [['«', '»'], ['“', '”']], 'ru' => [['«', '»'], ['„', '“']], 'uk' => [['«', '»'], ['„', '“']], 'pl' => [['„', '”'], ['«', '»']], 'cs' => [['„', '“'], ['‚', '‘']], 'nl' => [['‘', '’'], ['“', '”']], 'sv' => [['”', '”'], ['’', '’']], 'fi' => [['”', '”'], ['’', '’']], 'no' => [['«', '»'], ['‘', '’']], 'nb' => [['«', '»'], ['‘', '’']], 'ja' => [['「', '」'], ['『', '』']], 'he' => [['”', '”'], ['’', '’']], 'el' => [['«', '»'], ['“', '”']], 'hu' => [['„', '”'], ['»', '«']]];

    public static function quote(int &$depth, array $pairs, string $keyword): string
    {
        if ('open-quote' === $keyword || 'no-open-quote' === $keyword) {
            $pair = $pairs[min($depth, \count($pairs) - 1)] ?? null;
            ++$depth;

            return 'open-quote' === $keyword ? $pair[0] ?? '' : '';
        }
        // A close-quote without an open one draws nothing
        if (0 === $depth) {
            return '';
        }
        --$depth;
        $pair = $pairs[min($depth, \count($pairs) - 1)] ?? null;

        return 'close-quote' === $keyword ? $pair[1] ?? '' : '';
    }

    public static function quotePairs(string $value, string $language = ''): ?array
    {
        $value = trim($value);
        if ('auto' === strtolower($value) || 'initial' === strtolower($value) || 'match-parent' === strtolower($value)) {
            return self::LANGUAGE_QUOTES[strtolower(explode('-', $language)[0])] ?? [['“', '”'], ['‘', '’']];
        }
        if ('none' === strtolower($value)) {
            return [];
        }
        if (!preg_match_all('/"((?:\\\\.|[^"\\\\])*)"|\'((?:\\\\.|[^\'\\\\])*)\'|(\S+)/', $value, $tokens, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL)) {
            return null;
        }
        $strings = [];
        foreach ($tokens as $token) {
            if (null !== ($token[3] ?? null)) {
                return null;
            }
            $strings[] = GeneratedContent::unescape($token[1] ?? $token[2] ?? '');
        }
        if (0 !== \count($strings) % 2) {
            return null;
        }
        $pairs = [];
        for ($i = 0; $i < \count($strings); $i += 2) {
            $pairs[] = [$strings[$i], $strings[$i + 1]];
        }

        return $pairs;
    }

    private array $counters = [];
    private int $depth = 0;
    private int $quoteDepth = 0;
    private array $ordinals = [];

    public function __construct(private readonly CounterStyles $styles)
    {
    }

    public function enter(array $style): void
    {
        CounterState::apply($this->counters, $this->depth, $style);
        ++$this->depth;
    }

    public function leave(): void
    {
        CounterState::leave($this->counters, $this->depth);
        --$this->depth;
    }

    public function parts(\Dom\Element $element, array $style): ?array
    {
        if (null === $tokens = GeneratedContent::tokens($style['content'] ?? 'none')) {
            return null;
        }
        CounterState::apply($this->counters, $this->depth, $style);
        $language = '';
        for ($ancestor = $element; null !== $ancestor; $ancestor = $ancestor->parentElement) {
            if ($ancestor->hasAttribute('lang')) {
                $language = $ancestor->getAttribute('lang');
                break;
            }
        }
        $pairs = self::quotePairs($style['quotes'] ?? 'auto', $language) ?? [];
        $parts = [];
        $bytes = 0;
        foreach ($tokens as $token) {
            if (null !== $url = $token[9] ?? $token[10] ?? $token[11] ?? null) {
                if (\count($parts) >= 256) {
                    return null;
                }
                $parts[] = ['url' => $url];
                continue;
            }
            if (null !== ($token[5] ?? null)) {
                $text = CounterState::text($this->counters, strtolower($token[5]), $token[6] ?? '', $token[7] ?? null, $token[8] ?? null, $this->styles);
            } elseif (null !== ($token[4] ?? null)) {
                $text = self::quote($this->quoteDepth, $pairs, strtolower($token[4]));
            } elseif (null !== ($token[3] ?? null)) {
                $text = $element->getAttribute($token[3]) ?? '';
            } else {
                $text = GeneratedContent::unescape($token[1] ?? $token[2] ?? '');
            }
            $text = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', '', $text) ?? '';
            $bytes += \strlen($text);
            if ($bytes > 65536 || \count($parts) > 256) {
                return null;
            }
            $last = \count($parts) - 1;
            if ($last >= 0 && isset($parts[$last]['text'])) {
                $parts[$last]['text'] .= $text;
            } else {
                $parts[] = ['text' => $text];
            }
        }

        return $parts;
    }

    public function marker(\Dom\Element $element, array $style, array $pseudo): ?array
    {
        if (isset($pseudo['content']) && !\in_array($pseudo['content'], ['normal', ''], true)) {
            return $this->parts($element, $pseudo);
        }
        if ('none' !== ($style['list-style-image'] ?? 'none')) {
            if (null !== $parts = $this->parts($element, ['content' => $style['list-style-image']])) {
                return $parts;
            }
        }
        $type = $style['list-style-type'] ?? 'disc';
        if ('none' === $type) {
            return null;
        }
        if (preg_match('/^(["\']).*\1$/s', $type)) {
            return $this->parts($element, ['content' => $type]);
        }

        return [['text' => $this->styles->marker(ListOrdinals::of($element, $this->ordinals), $type)]];
    }
}
