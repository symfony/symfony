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

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\Renderer\Theme\ThemeInterface;
use Symfony\Component\Form\Renderer\Plugin\PluginInterface;

class DefaultRenderer implements RendererInterface
{
    private $field;

    private $template;

    private $theme;

    private $vars = array();

    private $changes = array();

    private $initialized = false;

    private $rendered = false;

    public function __construct(ThemeInterface $theme, $template)
    {
        $this->theme = $theme;
        $this->template = $template;
    }

    private function initialize()
    {
        if (!$this->initialized) {
            $this->initialized = true;

            // Make sure that plugins and set variables are applied in the
            // order they were added
            foreach ($this->changes as $key => $value) {
                if ($value instanceof PluginInterface) {
                    $value->setUp($this);
                } else {
                    $this->vars[$key] = $value;
                }
            }
        }
    }

    public function setTheme(ThemeInterface $theme)
    {
        $this->theme = $theme;
    }

    public function getTheme()
    {
        return $this->theme;
    }

    public function addPlugin(PluginInterface $plugin)
    {
        $this->initialized = false;
        $this->changes[] = $plugin;
    }

    public function setVar($name, $value)
    {
        if ($this->initialized) {
            $this->vars[$name] = $value;
        } else {
            $this->changes[$name] = $value;
        }
    }

    public function hasVar($name)
    {
        return array_key_exists($name, $this->vars);
    }

    public function getVar($name)
    {
        $this->initialize();

        // TODO exception handling
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

        return $this->render('widget', $vars);
    }

    public function getErrors(array $vars = array())
    {
        return $this->render('errors', $vars);
    }

    public function getRow(array $vars = array())
    {
        return $this->render('row', $vars);
    }

    public function getRest(array $vars = array())
    {
        return $this->render('rest', $vars);
    }

    /**
     * Renders the label of the given field
     *
     * @param FieldInterface $field  The field to render the label for
     * @param array $params          Additional variables passed to the template
     */
    public function getLabel($label = null, array $vars = array())
    {
        if (null !== $label) {
            $vars['label'] = $label;
        }

        return $this->render('label', $vars);
    }

    protected function render($block, array $vars = array())
    {
        $this->initialize();

        return $this->theme->render($this->template, $block, array_replace(
            $this->vars,
            $vars
        ));
    }
}