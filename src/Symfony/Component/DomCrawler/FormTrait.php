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

/**
 * Holds the form logic shared by the classic and the native form.
 *
 * The using class declares the members that name a node or a field type, so that
 * each of them keeps an exact signature, and holds its own field registry.
 *
 * @internal
 */
trait FormTrait
{
    use DomTraversalTrait;

    private const FIELD_TAGS = ['input', 'button', 'textarea', 'select'];

    /**
     * Sets the value of the fields.
     *
     * @param array $values An array of field values
     *
     * @return $this
     */
    public function setValues(array $values): static
    {
        foreach ($values as $name => $value) {
            $this->fields->set($name, $value);
        }

        return $this;
    }

    /**
     * Gets the field values as PHP.
     *
     * This method converts fields with the array notation
     * (like foo[bar] to arrays) like PHP does.
     */
    public function getPhpValues(): array
    {
        $values = [];
        foreach ($this->getValues() as $name => $value) {
            $qs = http_build_query([$name => $value], '', '&');
            if ($qs) {
                parse_str($qs, $expandedValue);
                $varName = substr($name, 0, \strlen(key($expandedValue)));
                $values[] = [$varName => current($expandedValue)];
            }
        }

        return array_replace_recursive([], ...$values);
    }

    /**
     * Gets the file field values as PHP.
     *
     * This method converts fields with the array notation
     * (like foo[bar] to arrays) like PHP does.
     * The returned array is consistent with the array for field values
     * (@see getPhpValues), rather than uploaded files found in $_FILES.
     * For a compound file field foo[bar] it will create foo[bar][name],
     * instead of foo[name][bar] which would be found in $_FILES.
     */
    public function getPhpFiles(): array
    {
        $values = [];
        foreach ($this->getFiles() as $name => $value) {
            $qs = http_build_query([$name => $value], '', '&');
            if ($qs) {
                parse_str($qs, $expandedValue);
                $varName = substr($name, 0, \strlen(key($expandedValue)));

                array_walk_recursive(
                    $expandedValue,
                    static function (&$value, $key) {
                        if (ctype_digit($value) && ('size' === $key || 'error' === $key)) {
                            $value = (int) $value;
                        }
                    }
                );

                reset($expandedValue);

                $values[] = [$varName => current($expandedValue)];
            }
        }

        return array_replace_recursive([], ...$values);
    }

    /**
     * Gets the URI of the form.
     *
     * The returned URI is not the same as the form "action" attribute.
     * This method merges the value if the method is GET to mimics
     * browser behavior.
     */
    public function getUri(): string
    {
        $uri = parent::getUri();

        if (!\in_array($this->getMethod(), ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            $currentParameters = [];
            if ($query = parse_url($uri, \PHP_URL_QUERY)) {
                parse_str($query, $currentParameters);
            }

            $queryString = http_build_query(array_merge($currentParameters, $this->getValues()), '', '&');

            $pos = strpos($uri, '?');
            $base = false === $pos ? $uri : substr($uri, 0, $pos);
            $uri = rtrim($base.'?'.$queryString, '?');
        }

        return $uri;
    }

    /**
     * Gets the form method.
     *
     * If no method is defined in the form, GET is returned.
     */
    public function getMethod(): string
    {
        if (null !== $this->method) {
            return $this->method;
        }

        // If the form was created from a button rather than the form node, check for HTML5 method override
        if ($this->button !== $this->node && $this->button->getAttribute('formmethod')) {
            return strtoupper($this->button->getAttribute('formmethod'));
        }

        return $this->node->getAttribute('method') ? strtoupper($this->node->getAttribute('method')) : 'GET';
    }

    /**
     * Gets the form name.
     *
     * If no name is defined on the form, an empty string is returned.
     */
    public function getName(): string
    {
        return $this->node->getAttribute('name') ?? '';
    }

    /**
     * Returns true if the named field exists.
     */
    public function has(string $name): bool
    {
        return $this->fields->has($name);
    }

    /**
     * Removes a field from the form.
     */
    public function remove(string $name): void
    {
        $this->fields->remove($name);
    }

    /**
     * Returns true if the named field exists.
     *
     * @param string $name The field name
     */
    public function offsetExists(mixed $name): bool
    {
        return $this->has($name);
    }

    /**
     * Sets the value of a field.
     *
     * @param string       $name  The field name
     * @param string|array $value The value of the field
     *
     * @throws \InvalidArgumentException if the field does not exist
     */
    public function offsetSet(mixed $name, mixed $value): void
    {
        $this->fields->set($name, $value);
    }

    /**
     * Removes a field from the form.
     *
     * @param string $name The field name
     */
    public function offsetUnset(mixed $name): void
    {
        $this->fields->remove($name);
    }

    protected function getRawUri(): string
    {
        // If the form was created from a button rather than the form node, check for HTML5 action overrides
        if ($this->button !== $this->node && $formAction = $this->button->getAttribute('formaction')) {
            return $formAction;
        }

        return $this->node->getAttribute('action') ?? '';
    }

    /**
     * Returns the nodes of the fields that belong to this form, in document order.
     *
     * @return list<\DOMElement|\Dom\Element>
     */
    private function collectFieldNodes(): array
    {
        if (!$this->node->hasAttribute('id')) {
            // only descendant elements belong to this form, and those carrying a form attribute belong to another one
            return self::collectDescendants($this->node, static fn ($node): bool => \in_array($node->localName, self::FIELD_TAGS, true)
                && !$node->hasAttribute('form')
                && self::isSubmittable($node));
        }

        // corresponding elements are either descendants of the form or carry a matching HTML5 form attribute,
        // so the whole document has to be walked
        $formId = $this->node->getAttribute('id');

        return self::collectDescendants($this->node->ownerDocument, static function ($node) use ($formId): bool {
            if (!\in_array($node->localName, self::FIELD_TAGS, true) || !self::isSubmittable($node)) {
                return false;
            }

            if ($node->hasAttribute('form')) {
                return $formId === $node->getAttribute('form');
            }

            return $formId === self::findAncestor($node, 'form')?->getAttribute('id');
        });
    }

    /**
     * Tells whether a field takes part in a submission.
     *
     * Fields inside a template are inert, unless a turbo-stream brings them back.
     */
    private static function isSubmittable(\DOMElement|\Dom\Element $node): bool
    {
        return !self::findAncestor($node, 'template') || self::findAncestor($node, 'turbo-stream');
    }
}
