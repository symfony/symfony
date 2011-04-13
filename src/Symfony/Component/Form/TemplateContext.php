<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Util\ChoiceUtil;

class TemplateContext implements \ArrayAccess, \IteratorAggregate
{
    static $cache;

    private $vars = array(
        'value' => null,
        'choices' => array(),
        'preferred_choices' => array(),
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

    static public function create(FormInterface $form)
    {
        if (null === self::$cache) {
            self::$cache = new \SplObjectStorage();
        }

        if (isset(self::$cache[$form])) {
            return self::$cache[$form];
        }

        // populate the cache for the root form
        $root = $form;
        while ($root->getParent()) {
            $root = $root->getParent();
        }

        self::$cache[$root] = new self($root);

        return self::$cache[$form];
    }

    private function __construct(FormInterface $form, self $parent = null)
    {
        $this->parent = $parent;

        $types = (array) $form->getTypes();
        $children = array();

        $this->set('context', $this);

        foreach ($types as $type) {
            $type->buildVariables($this, $form);
        }

        foreach ($form as $key => $child) {
            $children[$key] = self::$cache[$child] = new self($child, $this);
        }

        $this->setChildren($children);

        foreach ($types as $type) {
            $type->buildVariablesBottomUp($this, $form);
        }
    }

    public function set($name, $value)
    {
        $this->vars[$name] = $value;
    }

    public function has($name)
    {
        return array_key_exists($name, $this->vars);
    }

    public function get($name)
    {
        if (!isset($this->vars[$name])) {
            return null;
        }

        return $this->vars[$name];
    }

    public function all()
    {
        return $this->vars;
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

    public function getChoiceLabel($choice)
    {
        return isset($this->vars['choices'][$choice])
            ? $this->vars['choices'][$choice]
            : (isset($this->vars['preferred_choices'][$choice])
                ? $this->vars['preferred_choices'][$choice]
                : null
            );
    }

    public function isChoiceGroup($choice)
    {
        return is_array($choice) || $choice instanceof \Traversable;
    }

    public function isChoiceSelected($choice)
    {
        $choice = ChoiceUtil::toValidChoice($choice);

        // The value should already have been converted by value transformers,
        // otherwise we had to do the conversion on every call of this method
        if (is_array($this->vars['value'])) {
            return false !== array_search($choice, $this->vars['value'], true);
        }

        return $choice === $this->vars['value'];
    }
}
