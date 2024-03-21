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
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
abstract class DomFormField extends FormField
{
    protected \DOMElement|\DOM\Element $domNode;
    protected \DOMDocument|\DOM\Document $domDocument;
    protected \DOMXPath|\DOM\Xpath $domXpath;
    protected bool $disabled = false;

    /**
     * @param \DOMElement|\DOM\Element $node The node associated with this field
     */
    public function __construct(\DOMElement|\DOM\Element $node)
    {
        $this->domNode = $node;
        if ($node instanceof \DOMElement) {
            $this->node = $node;
        }

        $this->name = $node->getAttribute('name');

        if ($node->ownerDocument instanceof \DOM\Document) {
            $this->domXpath = new \DOM\XPath($node->ownerDocument);
        } else {
            $this->domXpath = new \DOMXPath($node->ownerDocument);
            $this->xpath = $this->domXpath;
        }

        $this->initialize();
    }

    /**
     * Returns the label tag associated to the field or null if none.
     *
     * @deprecated since Symfony 7.1, use `getDomLabel()` instead
     */
    public function getLabel(): ?\DOMElement
    {
        trigger_deprecation('symfony/dom-crawler', '7.1', 'The "%s()" method is deprecated, use "%s::getDomLabel()" instead.', __METHOD__, __CLASS__);

        $element = $this->getDomLabel();
        if ($element instanceof \DOM\Element) {
            throw new \LogicException(sprintf('The form is not using legacy DOM objects, you must use "%s::getDomLabel()" instead of "%s".', __CLASS__, __METHOD__));
        }

        return $element;
    }

    public function getDomLabel(): \DOMElement|\DOM\Element|null
    {
        if ($this->domNode->ownerDocument instanceof \DOM\Document) {
            $xpath = new \DOM\XPath($this->domNode->ownerDocument);
        } else {
            $xpath = new \DOMXPath($this->node->ownerDocument);
        }

        if ($this->domNode->hasAttribute('id')) {
            $labels = $xpath->query(sprintf('descendant::label[@for="%s"]', $this->domNode->getAttribute('id')));
            if ($labels->length > 0) {
                return $labels->item(0);
            }
        }

        $labels = $xpath->query('ancestor::label[1]', $this->domNode);

        return $labels->length > 0 ? $labels->item(0) : null;
    }

    /**
     * Check if the current field is disabled.
     */
    public function isDisabled(): bool
    {
        return $this->domNode->hasAttribute('disabled');
    }
}
