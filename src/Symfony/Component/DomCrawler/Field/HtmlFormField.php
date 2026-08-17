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
 * HtmlFormField is the abstract class for all form fields backed by the native HTML parser.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class HtmlFormField
{
    use DomTraversalTrait;
    use FormFieldTrait;

    protected string $name;
    protected string|array|null $value = null;
    protected bool $disabled = false;

    /**
     * @param \Dom\Element $node The node associated with this field
     */
    public function __construct(
        protected \Dom\Element $node,
    ) {
        $this->name = $node->getAttribute('name') ?? '';

        $this->initialize();
    }

    /**
     * Returns the label tag associated to the field or null if none.
     */
    public function getLabel(): ?\Dom\Element
    {
        if ($this->node->hasAttribute('id')) {
            $id = $this->node->getAttribute('id');
            $labels = self::collectDescendants($this->node->ownerDocument, static fn ($node) => 'label' === $node->localName && $id === $node->getAttribute('for'));

            if ($labels) {
                return $labels[0];
            }
        }

        return self::findAncestor($this->node, 'label');
    }
}
