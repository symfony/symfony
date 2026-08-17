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
 * HtmlTextareaFormField represents a textarea form field (an HTML textarea tag).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HtmlTextareaFormField extends HtmlFormField
{
    /**
     * Initializes the form field.
     *
     * @throws \LogicException When node type is incorrect
     */
    protected function initialize(): void
    {
        if ('textarea' !== $this->node->localName) {
            throw new \LogicException(\sprintf('An HtmlTextareaFormField can only be created from a textarea tag (%s given).', $this->node->localName));
        }

        // the HTML parser reads the content of a textarea as raw text, so what it
        // holds is already the value, comment-looking markup included
        $this->value = $this->node->textContent;
    }
}
