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
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @internal
 */
trait TextareaFormFieldTrait
{
    /**
     * Initializes the form field.
     *
     * @throws \LogicException When node type is incorrect
     */
    protected function initialize(): void
    {
        $nodeName = strtolower($this->node->nodeName);
        if ('textarea' !== $nodeName) {
            throw new \LogicException(\sprintf('A TextareaFormField can only be created from a textarea tag (%s given).', $nodeName));
        }

        $this->value = '';
        foreach ($this->node->childNodes as $node) {
            $this->value .= $node->wholeText;
        }
    }
}
