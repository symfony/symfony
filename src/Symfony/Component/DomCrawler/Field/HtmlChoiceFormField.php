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
 * HtmlChoiceFormField represents a choice form field.
 *
 * It is constructed from an HTML select tag, or an HTML checkbox, or radio inputs.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HtmlChoiceFormField extends HtmlFormField
{
    use ChoiceFormFieldTrait;

    /**
     * Adds a choice to the current ones.
     *
     * @throws \LogicException When choice provided is neither multiple, radio nor select,
     *                         or when the node tag does not match the field type
     */
    public function addChoice(\Dom\Element $node): void
    {
        $this->attachChoice($node);
    }

    /**
     * Initializes the form field.
     *
     * @throws \LogicException When node type is incorrect
     */
    protected function initialize(): void
    {
        if ('input' !== $this->node->localName && 'select' !== $this->node->localName) {
            throw new \LogicException(\sprintf('An HtmlChoiceFormField can only be created from an input or select tag (%s given).', $this->node->localName));
        }

        if ('input' === $this->node->localName) {
            $type = strtolower($this->node->getAttribute('type') ?? '');

            if ('checkbox' !== $type && 'radio' !== $type) {
                throw new \LogicException(\sprintf('An HtmlChoiceFormField can only be created from an input tag with a type of checkbox or radio (given type is "%s").', $this->node->getAttribute('type')));
            }
        }

        $this->initializeChoices();
    }
}
