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
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Translates messages into HTML: the tags of a message are rendered as HTML elements, everything else is escaped.
 *
 * @author Hugo Alliaume <hugo@alliau.me>
 */
final class HtmlTranslator
{
    private const VOID_ELEMENTS = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'];
    private const RAW_TEXT_ELEMENTS = ['script', 'style', 'iframe', 'noembed', 'noframes', 'noscript', 'xmp', 'plaintext'];

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, array<string, mixed>|list<array<string, mixed>>> $tags The HTML element (["tag" => name, "attr" => attributes]), or the list of nested HTML elements, to render for each tag name used in the message
     *
     * @throws InvalidArgumentException When a tag is not a valid HTML element definition
     */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null, array $tags = []): string
    {
        $wrappers = [];
        foreach ($tags as $name => $elements) {
            $wrappers[$name] = $this->buildWrapper($name, $elements);
        }

        if (method_exists($this->translator, 'getGlobalParameters')) {
            $parameters += $this->translator->getGlobalParameters();
        }

        foreach ($parameters as $key => $value) {
            if ($value instanceof TranslatableInterface) {
                $value = $value->trans($this->translator, $locale);
            }

            if (\is_string($value) || $value instanceof \Stringable) {
                $parameters[$key] = htmlspecialchars((string) $value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            }
        }

        $translated = $this->translator->trans($id, $parameters, $domain, $locale);

        return $this->replaceTags($translated, $wrappers) ?? htmlspecialchars($translated, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8', false);
    }

    /**
     * @return array{string, string} The opening and closing HTML
     */
    private function buildWrapper(string|int $name, mixed $elements): array
    {
        if (!\is_string($name) || !preg_match('/^[a-zA-Z][\w-]*$/D', $name)) {
            throw new InvalidArgumentException(\sprintf('The "%s" tag name must start with a letter and only contain letters, digits, "_" and "-".', $name));
        }

        // a single element, or an invalid value (including an empty list) that the loop rejects
        if (!\is_array($elements) || !$elements || !array_is_list($elements)) {
            $elements = [$elements];
        }

        $open = $close = '';
        foreach ($elements as $element) {
            if (!\is_array($element) || !\is_string($element['tag'] ?? null) || !\is_array($element['attr'] ?? []) || array_diff_key($element, ['tag' => true, 'attr' => true])) {
                throw new InvalidArgumentException(\sprintf('The "%s" tag must be an array with a "tag" key and an optional "attr" key, or a non-empty list of such arrays.', $name));
            }

            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9-]*$/D', $element['tag'])) {
                throw new InvalidArgumentException(\sprintf('The "%s" tag has an invalid HTML tag name "%s".', $name, $element['tag']));
            }

            if (\in_array(strtolower($element['tag']), self::RAW_TEXT_ELEMENTS, true)) {
                throw new InvalidArgumentException(\sprintf('The "%s" tag cannot use the "%s" HTML element as its content is not escaped.', $name, $element['tag']));
            }

            $open .= '<'.$element['tag'];
            foreach ($element['attr'] ?? [] as $attribute => $value) {
                if (!\is_string($attribute) || !preg_match('/^[^\s"\'>\/=\x00-\x1F\x7F]+$/D', $attribute)) {
                    throw new InvalidArgumentException(\sprintf('The "%s" tag has an invalid attribute name "%s".', $name, $attribute));
                }

                if (null === $value || false === $value) {
                    continue;
                }

                if (true === $value) {
                    $open .= ' '.$attribute;

                    continue;
                }

                if (!\is_scalar($value) && !$value instanceof \Stringable) {
                    throw new InvalidArgumentException(\sprintf('The "%s" attribute of the "%s" tag must be a scalar, a stringable object or null, "%s" given.', $attribute, $name, get_debug_type($value)));
                }

                $open .= ' '.$attribute.'="'.htmlspecialchars((string) $value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8').'"';
            }
            $open .= '>';

            if (!\in_array(strtolower($element['tag']), self::VOID_ELEMENTS, true)) {
                $close = '</'.$element['tag'].'>'.$close;
            }
        }

        return [$open, $close];
    }

    /**
     * @param array<string, array{string, string}> $wrappers
     *
     * @return string|null The HTML, or null when the message tags are not balanced
     */
    private function replaceTags(string $translated, array $wrappers): ?string
    {
        if (!$wrappers) {
            return null;
        }

        $names = implode('|', array_keys($wrappers));
        $parts = preg_split('#<(/?)('.$names.')\s*(/?)>#', $translated, -1, \PREG_SPLIT_DELIM_CAPTURE);

        $html = htmlspecialchars(array_shift($parts), \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8', false);
        $opened = [];
        foreach (array_chunk($parts, 4) as [$closing, $name, $selfClosing, $text]) {
            [$open, $close] = $wrappers[$name];

            if ('/' === $closing) {
                if ('/' === $selfClosing || array_pop($opened) !== $name) {
                    return null;
                }

                $html .= $close;
            } elseif ('/' === $selfClosing || '' === $close) {
                $html .= $open.$close;
            } else {
                $opened[] = $name;
                $html .= $open;
            }

            $html .= htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8', false);
        }

        return $opened ? null : $html;
    }
}
