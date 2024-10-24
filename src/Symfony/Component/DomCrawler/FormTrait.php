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

use Symfony\Component\DomCrawler\Field\FormField;

trait FormTrait
{
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
                    function (&$value, $key) {
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

        if (!\in_array($this->getMethod(), ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $query = parse_url($uri, \PHP_URL_QUERY);
            $currentParameters = [];
            if ($query) {
                parse_str($query, $currentParameters);
            }

            $queryString = http_build_query(array_merge($currentParameters, $this->getValues()), '', '&');

            $pos = strpos($uri, '?');
            $base = false === $pos ? $uri : substr($uri, 0, $pos);
            $uri = rtrim($base.'?'.$queryString, '?');
        }

        return $uri;
    }

    protected function getRawUri(): string
    {
        // If the form was created from a button rather than the form node, check for HTML5 action overrides
        if ($this->button !== $this->node && $this->button->getAttribute('formaction')) {
            return $this->button->getAttribute('formaction');
        }

        return $this->node->getAttribute('action') ?? '';
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
     * Gets all fields.
     *
     * @return FormField[]
     */
    public function all(): array
    {
        return $this->fields->all();
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

}
