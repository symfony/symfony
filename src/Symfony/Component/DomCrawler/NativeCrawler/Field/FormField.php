<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\NativeCrawler\Field;

use Symfony\Component\DomCrawler\Field\FormFieldTrait;

/**
 * FormField is the abstract class for all form fields.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
abstract class FormField
{
    use FormFieldTrait;

    protected \DOM\Document $document;
    protected \DOM\XPath $xpath;

    /**
     * @param \DOM\Element $node The node associated with this field
     */
    public function __construct(
        protected \DOM\Element $node,
    ) {
        $this->name = $node->getAttribute('name') ?? '';
        $this->xpath = new \DOM\XPath($node->ownerDocument);

        $this->initialize();
    }

    /**
     * Returns the label tag associated to the field or null if none.
     */
    public function getLabel(): ?\DOM\Element
    {
        $xpath = new \DOM\XPath($this->node->ownerDocument);

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
     * Check if the current field is disabled.
     */
    public function isDisabled(): bool
    {
        return $this->node->hasAttribute('disabled');
    }
}
