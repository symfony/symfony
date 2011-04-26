<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\Util\FormUtil;

class FormView implements \ArrayAccess, \IteratorAggregate, \Countable
{
    private $vars = array(
        'value' => null,
        'attr' => array(),
    );

    private $parent;

    private $children = array();

    /**
     * Is the form attached to this renderer rendered?
     *
     * Rendering happens when either the widget or the row method was called.
     * Row implicitly includes widget, however certain rendering mechanisms
     * have to skip widget rendering when a row is rendered.
     *
     * @var Boolean
     */
    private $rendered = false;

    /**
     * @param string $name
     * @param mixed $value
     */
    public function set($name, $value)
    {
        $this->vars[$name] = $value;
    }

    /**
     * @param $name
     * @return Boolean
     */
    public function has($name)
    {
        return array_key_exists($name, $this->vars);
    }

    /**
     * @param $name
     * @param $default
     * @return mixed
     */
    public function get($name, $default = null)
    {
        if (false === $this->has($name)) {
            return $default;
        }

        return $this->vars[$name];
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->vars;
    }

    /**
     * Alias of all so it is possible to do `form.vars.foo`
     *
     * @return array
     */
    public function getVars()
    {
        return $this->all();
    }

    public function setAttribute($name, $value)
    {
        $this->vars['attr'][$name] = $value;
    }

    public function isRendered()
    {
        return $this->rendered;
    }

    public function setRendered()
    {
        $this->rendered = true;
    }

    public function setParent(self $parent)
    {
        $this->parent = $parent;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function hasParent()
    {
        return null !== $this->parent;
    }

    public function setChildren(array $children)
    {
        $this->children = $children;
    }

    public function getChildren()
    {
        return $this->children;
    }

    public function hasChildren()
    {
        return count($this->children) > 0;
    }

    public function offsetGet($name)
    {
        return $this->children[$name];
    }

    public function offsetExists($name)
    {
        return isset($this->children[$name]);
    }

    public function offsetSet($name, $value)
    {
        throw new \BadMethodCallException('Not supported');
    }

    public function offsetUnset($name)
    {
        throw new \BadMethodCallException('Not supported');
    }

    public function getIterator()
    {
        if (isset($this->children)) {
            $this->rendered = true;

            return new \ArrayIterator($this->children);
        }

        return new \ArrayIterator(array());
    }

    public function isChoiceGroup($choice)
    {
        return is_array($choice) || $choice instanceof \Traversable;
    }

    public function isChoiceSelected($choice)
    {
        $choice = FormUtil::toArrayKey($choice);

        // The value should already have been converted by value transformers,
        // otherwise we had to do the conversion on every call of this method
        if (is_array($this->vars['value'])) {
            return false !== array_search($choice, $this->vars['value'], true);
        }

        return $choice === $this->vars['value'];
    }

    /**
     * @see Countable
     * @return integer
     */
    public function count()
    {
        return count($this->children);
    }
}
