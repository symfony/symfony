<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DomCrawler\Field;

/**
 * InputFormField represents an input form field (an HTML input tag).
 *
 * For inputs with type of file, checkbox, or radio, there are other more
 * specialized classes (cf. FileFormField and ChoiceFormField).
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class InputFormField extends FormField
{
    /**
     * Initializes the form field.
     *
     * @throws \LogicException When node type is incorrect
     */
    protected function initialize()
    {
        if ('input' !== $this->node->nodeName && 'button' !== $this->node->nodeName) {
            throw new \LogicException(sprintf('An InputFormField can only be created from an input or button tag (%s given).', $this->node->nodeName));
        }

        $type = strtolower($this->node->getAttribute('type'));
        if ('checkbox' === $type) {
            throw new \LogicException('Checkboxes should be instances of ChoiceFormField.');
        }

        if ('file' === $type) {
            throw new \LogicException('File inputs should be instances of FileFormField.');
        }

        $this->value = $this->node->getAttribute('value');
    }
}
