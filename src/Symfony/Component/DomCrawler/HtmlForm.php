<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler;

use Symfony\Component\DomCrawler\Field\HtmlChoiceFormField;
use Symfony\Component\DomCrawler\Field\HtmlFormField;

/**
 * HtmlForm represents an HTML form, backed by the native HTML parser.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @implements \ArrayAccess<string, HtmlFormField|array<HtmlFormField>>
 */
class HtmlForm extends HtmlLink implements \ArrayAccess
{
    use FormTrait;

    private \Dom\Element $button;
    private HtmlFormFieldRegistry $fields;

    /**
     * @param \Dom\Element $node       A \Dom\Element instance
     * @param string|null  $currentUri The URI of the page where the form is embedded
     * @param string|null  $method     The method to use for the link (if null, it defaults to the method defined by the form)
     * @param string|null  $baseHref   The URI of the <base> used for relative links, but not for empty action
     *
     * @throws \LogicException if the node is not a button inside a form tag
     */
    public function __construct(
        \Dom\Element $node,
        ?string $currentUri = null,
        ?string $method = null,
        private ?string $baseHref = null,
    ) {
        parent::__construct($node, $currentUri, $method);

        $this->initialize();
    }

    /**
     * Gets the form node associated with this form.
     */
    public function getFormNode(): \Dom\Element
    {
        return $this->node;
    }

    /**
     * Gets the field values.
     *
     * The returned array does not include file fields (@see getFiles).
     */
    public function getValues(): array
    {
        $values = [];
        foreach ($this->fields->all() as $name => $field) {
            if ($field->isDisabled()) {
                continue;
            }

            if (!$field instanceof Field\HtmlFileFormField && $field->hasValue()) {
                $values[$name] = $field->getValue();
            }
        }

        return $values;
    }

    /**
     * Gets the file field values.
     */
    public function getFiles(): array
    {
        if (!\in_array($this->getMethod(), ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            return [];
        }

        $files = [];

        foreach ($this->fields->all() as $name => $field) {
            if ($field->isDisabled()) {
                continue;
            }

            if ($field instanceof Field\HtmlFileFormField) {
                $files[$name] = $field->getValue();
            }
        }

        return $files;
    }

    /**
     * Gets a named field.
     *
     * @return HtmlFormField|HtmlFormField[]|HtmlFormField[][]
     *
     * @throws \InvalidArgumentException When field is not present in this form
     */
    public function get(string $name): HtmlFormField|array
    {
        return $this->fields->get($name);
    }

    /**
     * Sets a named field.
     */
    public function set(HtmlFormField $field): void
    {
        $this->fields->add($field);
    }

    /**
     * Gets all fields.
     *
     * @return HtmlFormField[]
     */
    public function all(): array
    {
        return $this->fields->all();
    }

    /**
     * Gets the value of a field.
     *
     * @param string $name The field name
     *
     * @return HtmlFormField|HtmlFormField[]|HtmlFormField[][]
     *
     * @throws \InvalidArgumentException if the field does not exist
     */
    public function offsetGet(mixed $name): HtmlFormField|array
    {
        return $this->fields->get($name);
    }

    /**
     * Disables validation.
     *
     * @return $this
     */
    public function disableValidation(): static
    {
        foreach ($this->fields->all() as $field) {
            if ($field instanceof HtmlChoiceFormField) {
                $field->disableValidation();
            }
        }

        return $this;
    }

    /**
     * Sets the node for the form.
     *
     * Expects a 'submit' button \Dom\Element and finds the corresponding form element, or the form element itself.
     *
     * @throws \LogicException If given node is not a button or input or does not have a form ancestor
     */
    protected function setNode(\Dom\Element $node): void
    {
        $this->button = $node;
        if ('button' === $node->localName || ('input' === $node->localName && \in_array(strtolower($node->getAttribute('type') ?? ''), ['submit', 'button', 'image'], true))) {
            if ($node->hasAttribute('form')) {
                // if the node has the HTML5-compliant 'form' attribute, use it
                $formId = $node->getAttribute('form');
                $form = $node->ownerDocument->getElementById($formId);
                if (null === $form) {
                    throw new \LogicException(\sprintf('The selected node has an invalid form attribute (%s).', $formId));
                }
                $this->node = $form;

                return;
            }
            // we loop until we find a form ancestor
            do {
                if (null === $node = $node->parentNode) {
                    throw new \LogicException('The selected node does not have a form ancestor.');
                }
            } while (!$node instanceof \Dom\Element || 'form' !== $node->localName);
        } elseif ('form' !== $node->localName) {
            throw new \LogicException(\sprintf('Unable to submit on a "%s" tag.', $node->localName));
        }

        $this->node = $node;
    }

    /**
     * Adds form elements related to this form.
     *
     * Creates an internal copy of the submitted 'button' element and
     * the form node or the entire document depending on whether we need
     * to find non-descendant elements through HTML5 'form' attribute.
     */
    private function initialize(): void
    {
        $this->fields = new HtmlFormFieldRegistry();

        // add submitted button if it has a valid name
        if ('form' !== $this->button->localName && $this->button->hasAttribute('name') && $this->button->getAttribute('name')) {
            if ('input' === $this->button->localName && 'image' === strtolower($this->button->getAttribute('type') ?? '')) {
                $name = $this->button->getAttribute('name');
                $this->button->setAttribute('value', '0');

                // temporarily change the name of the input node for the x coordinate
                $this->button->setAttribute('name', $name.'.x');
                $this->set(new Field\HtmlInputFormField($this->button));

                // temporarily change the name of the input node for the y coordinate
                $this->button->setAttribute('name', $name.'.y');
                $this->set(new Field\HtmlInputFormField($this->button));

                // restore the original name of the input node
                $this->button->setAttribute('name', $name);
            } else {
                $this->set(new Field\HtmlInputFormField($this->button));
            }
        }

        foreach ($this->collectFieldNodes() as $node) {
            $this->addField($node);
        }

        if ($this->baseHref && '' !== ($this->node->getAttribute('action') ?? '')) {
            $this->currentUri = $this->baseHref;
        }
    }

    private function addField(\Dom\Element $node): void
    {
        if (!$node->hasAttribute('name') || !$node->getAttribute('name')) {
            return;
        }

        $localName = $node->localName;
        $type = strtolower($node->getAttribute('type') ?? '');

        if ('select' === $localName || 'input' === $localName && 'checkbox' === $type) {
            $this->set(new HtmlChoiceFormField($node));
        } elseif ('input' === $localName && 'radio' === $type) {
            // there may be other fields with the same name that are no choice
            // fields already registered
            $field = $this->has($node->getAttribute('name')) ? $this->get($node->getAttribute('name')) : null;

            if ($field instanceof HtmlChoiceFormField) {
                $field->addChoice($node);
            } else {
                $this->set(new HtmlChoiceFormField($node));
            }
        } elseif ('input' === $localName && 'file' === $type) {
            $this->set(new Field\HtmlFileFormField($node));
        } elseif ('input' === $localName && !\in_array($type, ['submit', 'button', 'image'], true)) {
            $this->set(new Field\HtmlInputFormField($node));
        } elseif ('textarea' === $localName) {
            $this->set(new Field\HtmlTextareaFormField($node));
        }
    }
}
