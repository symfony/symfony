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
 * TextareaFormField represents a textarea form field (an HTML textarea tag).
 *
 * @template T of \Dom\Element|\DOMElement
 *
 * @extends FormField<T>
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TextareaFormField extends FormField
{
    /**
     * Initializes the form field.
     *
     * @throws \LogicException When node type is incorrect
     */
    protected function initialize(): void
    {
        if ('textarea' !== strtolower($this->node->nodeName)) {
            throw new \LogicException(\sprintf('A TextareaFormField can only be created from a textarea tag (%s given).', $this->node->nodeName));
        }

        $this->value = '';
        foreach ($this->node->childNodes as $node) {
            $this->value .= $node->wholeText;
        }
    }
}
