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
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TextareaFormField extends DomFormField
{
    /**
     * Initializes the form field.
     *
     * @throws \LogicException When node type is incorrect
     */
    protected function initialize(): void
    {
        $nodeName = strtolower($this->domNode->nodeName);
        if ('textarea' !== $nodeName) {
            throw new \LogicException(\sprintf('A TextareaFormField can only be created from a textarea tag (%s given).', $nodeName));
        }

        $this->value = '';
        foreach ($this->domNode->childNodes as $node) {
            $this->value .= $node->wholeText;
        }
    }
}
