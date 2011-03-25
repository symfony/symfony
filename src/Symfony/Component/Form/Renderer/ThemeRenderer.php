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
use Symfony\Component\Form\Renderer\Theme\FormThemeInterface;
use Symfony\Component\Form\Renderer\Theme\FormThemeFactoryInterface;

class ThemeRenderer implements FormRendererInterface, \ArrayAccess, \IteratorAggregate
{
    private $form;

    private $block;

    private $themeFactory;

    private $theme;

    private $vars;

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

    private $children = array();

    public function __construct(FormInterface $form, FormThemeFactoryInterface $themeFactory, $template = null, ThemeRenderer $parent = null)
    {
        $this->form = $form;
        $this->themeFactory = $themeFactory;
        $this->parent = $parent;
        $this->vars = new RendererVarBag();

        if (null !== $template) {
            $this->setTemplate($template);
        }

        $types = (array)$form->getTypes();

        foreach ($types as $type) {
            $type->buildRenderer($this, $form);
        }

        foreach ($form as $key => $child) {
            $this->children[$key] = new self($child, $themeFactory, null, $parent);
        }

        foreach ($types as $type) {
            $type->buildRendererBottomUp($this, $form);
        }
    }

    public function setTemplate($template)
    {
        $this->setTheme($this->themeFactory->create($template));
    }

    public function setTheme(FormThemeInterface $theme)
    {
        $this->theme = $theme;
    }

    public function getTheme()
    {
        return $this->theme;
    }

    public function setBlock($block)
    {
        $this->block = $block;
    }

    public function getBlock()
    {
        return $this->block;
    }

    public function setVar($name, $value)
    {
        $this->vars[$name] = $value;
    }

    public function setAttribute($name, $value)
    {
        // handling through $this->changes not necessary
        $this->vars['attr'][$name] = $value;
    }

    public function hasVar($name)
    {
        return array_key_exists($name, $this->vars);
    }

    public function getVar($name)
    {
        // TODO exception handling
        if (isset($this->vars[$name])) {
            return $this->vars[$name];
        }
        return null;
    }

    public function getVars()
    {
        $this->initialize();

        return $this->vars;
    }

    public function isRendered()
    {
        return $this->rendered;
    }

    public function getWidget(array $vars = array())
    {
        $this->rendered = true;

        return $this->render('widget', $vars);
    }

    public function getErrors(array $vars = array())
    {
        return $this->render('errors', $vars);
    }

    public function getRow(array $vars = array())
    {
        $this->rendered = true;

        return $this->render('row', $vars);
    }

    public function getRest(array $vars = array())
    {
        return $this->render('rest', $vars);
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

        return $this->render('label', $vars);
    }

    public function getEnctype()
    {
        return $this->render('enctype', $this->vars);
    }

    protected function render($part, array $vars = array())
    {
        return $this->theme->render($this->block, $part, array_replace(
            $this->vars,
            $vars
        ));
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function getChildren()
    {
        return $this->children;
    }

    public function offsetGet($name)
    {
        $this->initialize();
        return $this->vars['fields'][$name];
    }

    public function offsetExists($name)
    {
        $this->initialize();
        return isset($this->vars['fields'][$name]);
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
        $this->initialize();

        if (isset($this->vars['fields'])) {
            $this->rendered = true;
            return new \ArrayIterator($this->vars['fields']);
        }
        return new \ArrayIterator(array());
    }
}
