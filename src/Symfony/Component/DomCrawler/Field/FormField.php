<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Field;

/**
 * FormField is the abstract class for all form fields.
 *
 * @template T of \Dom\Element|\DOMElement
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class FormField
{
    protected string $name;

    protected string|array|null $value = null;

    /**
     * @var (T is \Dom\Element ? \Dom\Document : \DOMDocument)
     */
    protected \Dom\Document|\DOMDocument $document;

    /**
     * @var (T is \Dom\Element ? \Dom\XPath : \DOMXPath)
     */
    protected \Dom\XPath|\DOMXPath $xpath;

    protected bool $disabled = false;

    /**
     * @param T $node The DOM node representing the form field
     */
    public function __construct(
        protected \Dom\Element|\DOMElement $node,
    ) {
        $this->name = $node->getAttribute('name') ?? '';

        if ($node instanceof \Dom\Element) {
            $this->xpath = new \Dom\XPath($node->ownerDocument);
        } else {
            $this->xpath = new \DOMXPath($node->ownerDocument);
        }

        $this->initialize();
    }

    /**
     * Returns the label tag associated to the field or null if none.
     *
     * @return T|null
     */
    public function getLabel(): \Dom\Element|\DOMElement|null
    {
        $xpath = $this->xpath;
        $xpath = new $xpath($this->node->ownerDocument);

        if ($this->node->hasAttribute('id')) {
            $labels = $xpath->query(\sprintf('descendant::label[@for="%s"]', $this->node->getAttribute('id')));
            if ($labels->length > 0) {
                return $labels->item(0);
            }
        }

        $labels = $xpath->query('ancestor::label[1]', $this->node);

        return $labels->length > 0 ? $labels->item(0) : null;
    }

    /**
     * Returns the name of the field.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the value of the field.
     */
    public function getValue(): string|array|null
    {
        return $this->value;
    }

    /**
     * Sets the value of the field.
     */
    public function setValue(?string $value): void
    {
        $this->value = $value ?? '';
    }

    /**
     * Returns true if the field should be included in the submitted values.
     */
    public function hasValue(): bool
    {
        return true;
    }

    /**
     * Check if the current field is disabled.
     */
    public function isDisabled(): bool
    {
        return $this->node->hasAttribute('disabled');
    }

    /**
     * Initializes the form field.
     */
    abstract protected function initialize(): void;
}
