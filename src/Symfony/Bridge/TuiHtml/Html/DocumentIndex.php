<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Html;

/**
 * The elements of a document that name others by their id: the <label for>, the elements whose aria-labelledby or
 * aria-describedby name them, the controls whose form attribute names their form. What HtmlView::setInnerHtml() looks
 * up across the document when the content of an element changes, without going through the document: built once, then
 * kept up to date with the content replaced.
 *
 * @internal
 */
final class DocumentIndex
{
    private const SELECTOR = 'label[for], [aria-labelledby], [aria-describedby], input[form], select[form], textarea[form], button[form]';

    /** @var array<string, array<int, \Dom\Element>> The <label for> of each id, by the object id of the label */
    private array $labels = [];
    /** @var array<string, array<int, \Dom\Element>> The elements whose aria-labelledby or aria-describedby names each id */
    private array $references = [];
    /** @var array<string, array<int, \Dom\Element>> The controls whose form attribute names each id */
    private array $controls = [];

    public function __construct(\Dom\Element $root)
    {
        $this->add($root);
    }

    /**
     * Indexes the elements in an element, added to the document.
     */
    public function add(\Dom\Element $root): void
    {
        foreach ($root->querySelectorAll(self::SELECTOR) as $element) {
            $this->index($element, true);
        }
    }

    /**
     * Forgets the elements in an element, leaving the document.
     */
    public function remove(\Dom\Element $root): void
    {
        foreach ($root->querySelectorAll(self::SELECTOR) as $element) {
            $this->index($element, false);
        }
    }

    /**
     * The <label for> naming an id, in the order of the document.
     *
     * @return list<\Dom\Element>
     */
    public function labelsFor(string $id): array
    {
        return self::ordered($this->labels[$id] ?? []);
    }

    /**
     * The elements whose aria-labelledby or aria-describedby names one of the ids.
     *
     * @param list<string> $ids
     *
     * @return list<\Dom\Element>
     */
    public function referencing(array $ids): array
    {
        return self::union($this->references, $ids);
    }

    /**
     * The controls whose form attribute names one of the ids.
     *
     * @param list<string> $ids
     *
     * @return list<\Dom\Element>
     */
    public function controlsOf(array $ids): array
    {
        return self::union($this->controls, $ids);
    }

    private function index(\Dom\Element $element, bool $add): void
    {
        $key = spl_object_id($element);
        $tagName = strtolower($element->localName);
        if ('label' === $tagName && null !== $for = $element->getAttribute('for')) {
            self::toggle($this->labels, $for, $key, $element, $add);
        }
        foreach (['aria-labelledby', 'aria-describedby'] as $attribute) {
            foreach (preg_split('/\s+/', trim($element->getAttribute($attribute) ?? ''), -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $id) {
                self::toggle($this->references, $id, $key, $element, $add);
            }
        }
        if (\in_array($tagName, ['input', 'select', 'textarea', 'button'], true) && null !== $form = $element->getAttribute('form')) {
            self::toggle($this->controls, $form, $key, $element, $add);
        }
    }

    /**
     * @param array<string, array<int, \Dom\Element>> $map
     */
    private static function toggle(array &$map, string $id, int $key, \Dom\Element $element, bool $add): void
    {
        if ($add) {
            $map[$id][$key] = $element;
        } elseif (isset($map[$id][$key])) {
            unset($map[$id][$key]);
            if (!$map[$id]) {
                unset($map[$id]);
            }
        }
    }

    /**
     * @param array<string, array<int, \Dom\Element>> $map
     * @param list<string>                            $ids
     *
     * @return list<\Dom\Element>
     */
    private static function union(array $map, array $ids): array
    {
        $elements = [];
        foreach ($ids as $id) {
            $elements += $map[$id] ?? [];
        }

        return array_values($elements);
    }

    /**
     * @param array<int, \Dom\Element> $elements
     *
     * @return list<\Dom\Element>
     */
    private static function ordered(array $elements): array
    {
        $elements = array_values($elements);
        if (\count($elements) > 1) {
            usort($elements, static fn (\Dom\Element $a, \Dom\Element $b) => $a->compareDocumentPosition($b) & \Dom\Node::DOCUMENT_POSITION_FOLLOWING ? -1 : 1);
        }

        return $elements;
    }
}
