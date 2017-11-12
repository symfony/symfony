<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * Represents an Accept-* header item.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class AcceptHeaderItem
{
    private $value;
    private $quality = 1.0;
    private $index = 0;
    private $attributes = array();

    public function __construct(string $value, array $attributes = array())
    {
        $this->value = $value;
        foreach ($attributes as $name => $value) {
            $this->setAttribute($name, $value);
        }
    }

    /**
     * Builds an AcceptHeaderInstance instance from a string.
     */
    public static function fromString(string $itemValue): self
    {
        $bits = preg_split('/\s*(?:;*("[^"]+");*|;*(\'[^\']+\');*|;+)\s*/', $itemValue, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $value = array_shift($bits);
        $attributes = array();

        $lastNullAttribute = null;
        foreach ($bits as $bit) {
            if (($start = substr($bit, 0, 1)) === ($end = substr($bit, -1)) && ('"' === $start || '\'' === $start)) {
                $attributes[$lastNullAttribute] = substr($bit, 1, -1);
            } elseif ('=' === $end) {
                $lastNullAttribute = $bit = substr($bit, 0, -1);
                $attributes[$bit] = null;
            } else {
                $parts = explode('=', $bit);
                $attributes[$parts[0]] = isset($parts[1]) && strlen($parts[1]) > 0 ? $parts[1] : '';
            }
        }

        return new self(($start = substr($value, 0, 1)) === ($end = substr($value, -1)) && ('"' === $start || '\'' === $start) ? substr($value, 1, -1) : $value, $attributes);
    }

    /**
     * Returns header  value's string representation.
     */
    public function __toString(): string
    {
        $string = $this->value.($this->quality < 1 ? ';q='.$this->quality : '');
        if (count($this->attributes) > 0) {
            $string .= ';'.implode(';', array_map(function ($name, $value) {
                return sprintf(preg_match('/[,;=]/', $value) ? '%s="%s"' : '%s=%s', $name, $value);
            }, array_keys($this->attributes), $this->attributes));
        }

        return $string;
    }

    /**
     * Set the item value.
     *
     *
     * @return $this
     */
    public function setValue(string $value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Returns the item value.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Set the item quality.
     *
     *
     * @return $this
     */
    public function setQuality(float $quality)
    {
        $this->quality = $quality;

        return $this;
    }

    /**
     * Returns the item quality.
     */
    public function getQuality(): float
    {
        return $this->quality;
    }

    /**
     * Set the item index.
     *
     *
     * @return $this
     */
    public function setIndex(int $index)
    {
        $this->index = $index;

        return $this;
    }

    /**
     * Returns the item index.
     */
    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * Tests if an attribute exists.
     */
    public function hasAttribute(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Returns an attribute by its name.
     *
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getAttribute(string $name, $default = null)
    {
        return isset($this->attributes[$name]) ? $this->attributes[$name] : $default;
    }

    /**
     * Returns all attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set an attribute.
     *
     *
     * @return $this
     */
    public function setAttribute(string $name, string $value)
    {
        if ('q' === $name) {
            $this->quality = (float) $value;
        } else {
            $this->attributes[$name] = (string) $value;
        }

        return $this;
    }
}
