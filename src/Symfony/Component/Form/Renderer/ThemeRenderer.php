<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Renderer;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Renderer\Theme\ThemeInterface;
use Symfony\Component\Form\Renderer\Theme\ThemeFactoryInterface;

class ThemeRenderer implements ThemeRendererInterface, \ArrayAccess, \IteratorAggregate
{
    private $blockHistory = array();

    private $themeFactory;

    private $theme;

    private $vars = array(
        'value' => null,
        'choices' => array(),
        'preferred_choices' => array(),
        'attr' => array(),
    );

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

    private $parent;

    private $children = array();

    public function __construct(ThemeFactoryInterface $themeFactory)
    {
        $this->themeFactory = $themeFactory;
    }

    public function setParent(self $parent)
    {
        $this->parent = $parent;
    }

    public function setChildren(array $children)
    {
        $this->children = $children;
    }

    public function setTemplate($template)
    {
        $this->setTheme($this->themeFactory->create($template));
    }

    public function setTheme(ThemeInterface $theme)
    {
        $this->theme = $theme;
    }

    public function getTheme()
    {
        if (!$this->theme && $this->parent) {
            return $this->parent->getTheme();
        }

        return $this->theme;
    }

    public function setBlock($block)
    {
        array_unshift($this->blockHistory, $block);
    }

    public function getBlock()
    {
        reset($this->blockHistory);

        return current($this->block);
    }

    public function setVar($name, $value)
    {
        $this->vars[$name] = $value;
    }

    public function setAttribute($name, $value)
    {
        $this->vars['attr'][$name] = $value;
    }

    public function hasVar($name)
    {
        return array_key_exists($name, $this->vars);
    }

    public function getVar($name)
    {
        if (!isset($this->vars[$name])) {
            return null;
        }

        return $this->vars[$name];
    }

    public function getVars()
    {
        return $this->vars;
    }

    public function isRendered()
    {
        return $this->rendered;
    }

    public function getWidget(array $vars = array())
    {
        $this->rendered = true;

        return $this->renderPart('widget', $vars);
    }

    public function getErrors(array $vars = array())
    {
        return $this->renderPart('errors', $vars);
    }

    public function getRow(array $vars = array())
    {
        $this->rendered = true;

        return $this->renderPart('row', $vars);
    }

    public function getRest(array $vars = array())
    {
        return $this->renderPart('rest', $vars);
    }

    /**
     * Renders the label of the given form
     *
     * @param FormInterface $form  The form to render the label for
     * @param array $params          Additional variables passed to the block
     */
    public function getLabel($label = null, array $vars = array())
    {
        if (null !== $label) {
            $vars['label'] = $label;
        }

        return $this->renderPart('label', $vars);
    }

    public function getEnctype()
    {
        return $this->renderPart('enctype', $this->vars);
    }

    protected function renderPart($part, array $vars = array())
    {
        return $this->getTheme()->render($this->blockHistory, $part, array_replace(
            $this->vars,
            $vars
        ));
    }

    public function render(array $vars = array())
    {
        return $this->getWidget($vars);
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function hasParent()
    {
        return null !== $this->parent;
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
                ? $this->cars['preferred_choices'][$choice]
                : null
            );
    }

    public function isChoiceGroup($choice)
    {
        return is_array($choice) || $choice instanceof \Traversable;
    }

    public function isChoiceSelected($choice)
    {
        $choice = $this->toValidArrayKey($choice);
        $choices = array_flip((array)$this->vars['value']);

        return array_key_exists($choice, $choices);
    }

    /**
     * Returns a valid array key for the given value
     *
     * @return integer|string $value  An integer if the value can be transformed
     *                                to one, a string otherwise
     */
    private function toValidArrayKey($value)
    {
        if ((string)(int)$value === (string)$value) {
            return (int)$value;
        }

        if (is_bool($value)) {
            return (int)$value;
        }

        return (string)$value;
    }
}
