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

    private $plugins = array();

    private $initialized = false;

    public function __construct(ThemeInterface $theme, $template)
    {
        $this->theme = $theme;
        $this->template = $template;
    }

    private function setUpPlugins()
    {
        if (!$this->initialized) {
            $this->initialized = true;

            foreach ($this->plugins as $plugin) {
                $plugin->setUp($this);
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
        $this->plugins[] = $plugin;
    }

    public function setVar($name, $value)
    {
        $this->vars[$name] = $value;
    }

    public function getVar($name)
    {
        $this->setUpPlugins();

        // TODO exception handling
        return $this->vars[$name];
    }

    public function getWidget(array $vars = array())
    {
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

    public function getHidden(array $vars = array())
    {
        return $this->render('hidden', $vars);
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
        $this->setUpPlugins();

        return $this->theme->render($this->template, $block, array_replace(
            $this->vars,
            $vars
        ));
    }
}