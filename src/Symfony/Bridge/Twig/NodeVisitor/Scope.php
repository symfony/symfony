<?php

namespace Symfony\Bridge\Twig\NodeVisitor;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class Scope
{
    /**
     * @var Scope|null
     */
    private $parent;

    /**
     * @var Scope[]
     */
    private $children;

    /**
     * @var array
     */
    private $data;

    /**
     * @param Scope $parent
     */
    public function __construct(Scope $parent = null)
    {
        $this->parent = $parent;
    }

    /**
     * Opens a new child scope.
     *
     * @return Scope
     */
    public function open()
    {
        $child = new self($this);
        $this->children[] = $child;

        return $child;
    }

    /**
     * Closes current scope and returns parent one.
     *
     * @return Scope|null
     */
    public function close()
    {
        return $this->parent;
    }

    /**
     * Stores data into current scope.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return Scope Current scope
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Returns data visible from current scope.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }

        if (null === $this->parent) {
            return $default;
        }

        return $this->parent->get($key, $default);
    }
}
