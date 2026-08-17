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

use Symfony\Component\DomCrawler\DomTraversalTrait;

/**
 * FormField is the abstract class for all form fields.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class FormField
{
    use DomTraversalTrait;
    use FormFieldTrait;

    protected string $name;
    protected string|array|null $value = null;

    /**
     * @deprecated since Symfony 8.2, not used anymore
     */
    protected \DOMDocument $document;

    /**
     * @deprecated since Symfony 8.2, not used anymore
     */
    protected \DOMXPath $xpath;

    protected bool $disabled = false;

    /**
     * @param \DOMElement $node The node associated with this field
     */
    public function __construct(
        protected \DOMElement $node,
    ) {
        $this->name = $node->getAttribute('name');
        $this->xpath = new \DOMXPath($node->ownerDocument);

        $this->initialize();
    }

    /**
     * Returns the label tag associated to the field or null if none.
     */
    public function getLabel(): ?\DOMElement
    {
        if ($this->node->hasAttribute('id')) {
            $id = $this->node->getAttribute('id');

            foreach ($this->node->ownerDocument->getElementsByTagName('label') as $label) {
                if ($id === $label->getAttribute('for')) {
                    return $label;
                }
            }
        }

        return self::findAncestor($this->node, 'label');
    }
}
