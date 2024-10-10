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
trait InputFormFieldTrait
{
    /**
     * Initializes the form field.
     *
     * @throws \LogicException When node type is incorrect
     */
    protected function initialize(): void
    {
        $nodeName = strtolower($this->node->nodeName);
        if ('input' !== $nodeName && 'button' !== $nodeName) {
            throw new \LogicException(\sprintf('An InputFormField can only be created from an input or button tag (%s given).', $nodeName));
        }

        $type = strtolower($this->node->getAttribute('type'));
        if ('checkbox' === $type) {
            throw new \LogicException('Checkboxes should be instances of ChoiceFormField.');
        }

        if ('file' === $type) {
            throw new \LogicException('File inputs should be instances of FileFormField.');
        }

        $this->value = $this->node->getAttribute('value') ?? '';
    }
}
