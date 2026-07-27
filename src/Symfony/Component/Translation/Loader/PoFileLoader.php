<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Loader;

use Symfony\Component\Translation\Exception\InvalidResourceException;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * @copyright Copyright (c) 2010, Union of RAD https://github.com/UnionOfRAD/lithium
 * @copyright Copyright (c) 2012, Clemens Tolboom
 */
class PoFileLoader extends FileLoader
{
    private array $metadata = [];
    private array $contexts = [];

    public function load(mixed $resource, string $locale, string $domain = 'messages'): MessageCatalogue
    {
        $this->metadata = [];
        $this->contexts = [];
        $catalogue = parent::load($resource, $locale, $domain);

        foreach ($this->metadata as $id => $metadata) {
            $catalogue->setMetadata($id, $metadata, $domain);
        }

        return $catalogue;
    }

    /**
     * Parses portable object (PO) format.
     *
     * From https://www.gnu.org/software/gettext/manual/gettext.html#PO-Files
     * we should be able to parse files having:
     *
     * white-space
     * #  translator-comments
     * #. extracted-comments
     * #: reference...
     * #, flag...
     * #| msgid previous-untranslated-string
     * msgid untranslated-string
     * msgstr translated-string
     *
     * extra or different lines are:
     *
     * #| msgctxt previous-context
     * #| msgid previous-untranslated-string
     * msgctxt context
     *
     * #| msgid previous-untranslated-string-singular
     * #| msgid_plural previous-untranslated-string-plural
     * msgid untranslated-string-singular
     * msgid_plural untranslated-string-plural
     * msgstr[0] translated-string-case-0
     * ...
     * msgstr[N] translated-string-case-n
     *
     * The definition states:
     * - white-space and comments are optional.
     * - msgid "" that an empty singleline defines a header.
     *
     * This parser sacrifices some features of the reference implementation the
     * differences to that implementation are as follows.
     * - No support for comments spanning multiple lines.
     * - Translator and extracted comments are treated as being the same type.
     * - Message IDs are allowed to have other encodings as just US-ASCII.
     * - Contexts (msgctxt) are stored in the message metadata but ignored in the
     *   message key, so reusing a msgid with a different context is not supported.
     *
     * Items with an empty id are ignored.
     */
    protected function loadResource(string $resource): array
    {
        $stream = fopen($resource, 'r');

        $defaults = [
            'ids' => [],
            'translated' => null,
            'context' => null,
        ];

        $messages = [];
        $item = $defaults;
        $flags = [];

        while ($line = fgets($stream)) {
            $line = trim($line);

            if ('' === $line) {
                // Whitespace indicated current item is done
                $this->saveItem($messages, $item, $flags, $defaults);
            } elseif (str_starts_with($line, '#,')) {
                // flags belong to the next entry, so the previous one ends here
                if (null !== $item['translated']) {
                    $this->saveItem($messages, $item, $flags, $defaults);
                }
                $flags = array_map('trim', explode(',', substr($line, 2)));
            } elseif (str_starts_with($line, 'msgctxt "')) {
                // msgctxt always precedes its msgid, so the context belongs to the next entry
                if (null !== $item['translated']) {
                    $this->saveItem($messages, $item, $flags, $defaults);
                }
                $item['context'] = substr($line, 9, -1);
            } elseif (str_starts_with($line, 'msgid "')) {
                // We start a new msg so save previous
                if ($item['ids']) {
                    $this->saveItem($messages, $item, $flags, $defaults);
                }
                $item['ids']['singular'] = substr($line, 7, -1);
            } elseif (str_starts_with($line, 'msgstr "')) {
                $item['translated'] = substr($line, 8, -1);
            } elseif ('"' === $line[0]) {
                $continues = isset($item['translated']) ? 'translated' : ($item['ids'] ? 'ids' : 'context');

                if (\is_array($item[$continues])) {
                    end($item[$continues]);
                    $item[$continues][key($item[$continues])] .= substr($line, 1, -1);
                } else {
                    $item[$continues] .= substr($line, 1, -1);
                }
            } elseif (str_starts_with($line, 'msgid_plural "')) {
                $item['ids']['plural'] = substr($line, 14, -1);
            } elseif (str_starts_with($line, 'msgstr[')) {
                $size = strpos($line, ']');
                $item['translated'][(int) substr($line, 7, 1)] = substr($line, $size + 3, -1);
            }
        }
        // save last item
        $this->saveItem($messages, $item, $flags, $defaults);
        fclose($stream);

        return $messages;
    }

    private function saveItem(array &$messages, array &$item, array &$flags, array $defaults): void
    {
        if (!\in_array('fuzzy', $flags, true)) {
            $this->addMessage($messages, $item);
        }
        $item = $defaults;
        $flags = [];
    }

    /**
     * Save a translation item to the messages.
     *
     * A .po file could contain by error missing plural indexes. We need to
     * fix these before saving them.
     */
    private function addMessage(array &$messages, array $item): void
    {
        if (!empty($item['ids']['singular'])) {
            $id = stripcslashes($item['ids']['singular']);
            if (isset($item['ids']['plural'])) {
                $id .= '|'.stripcslashes($item['ids']['plural']);
            }

            $translated = (array) $item['translated'];
            // PO are by definition indexed so sort by index.
            ksort($translated);
            // Make sure every index is filled.
            end($translated);
            $count = key($translated);
            // Fill missing spots with '-'.
            $empties = array_fill(0, $count + 1, '-');
            $translated += $empties;
            ksort($translated);

            $context = null !== $item['context'] ? stripcslashes($item['context']) : null;
            if (\array_key_exists($id, $this->contexts) && $context !== $this->contexts[$id]) {
                throw new InvalidResourceException(null === $context || null === $this->contexts[$id]
                    ? \sprintf('The "%s" message is defined both with and without a context, which is not supported because contexts are ignored in message keys.', $id)
                    : \sprintf('The "%s" message is defined twice with different contexts ("%s" and "%s"), which is not supported because contexts are ignored in message keys.', $id, $this->contexts[$id], $context));
            }
            $this->contexts[$id] = $context;

            $messages[$id] = stripcslashes(implode('|', $translated));

            if (null !== $context) {
                $this->metadata[$id] = ['context' => $context];
            }
        }
    }
}
