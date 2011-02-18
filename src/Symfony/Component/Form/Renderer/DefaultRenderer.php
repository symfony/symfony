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

    private $parameters = array();

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

    public function setParameter($name, $value)
    {
        $this->parameters[$name] = $value;
    }

    public function getWidget(array $attributes = array(), array $parameters = array())
    {
        return $this->render('widget', $attributes, $parameters);
    }

    public function getErrors(array $attributes = array(), array $parameters = array())
    {
        return $this->render('errors', $attributes, $parameters);
    }

    public function getRow(array $attributes = array(), array $parameters = array())
    {
        return $this->render('row', $attributes, $parameters);
    }

    public function getHidden(array $attributes = array(), array $parameters = array())
    {
        return $this->render('hidden', $attributes, $parameters);
    }

    /**
     * Renders the label of the given field
     *
     * @param FieldInterface $field  The field to render the label for
     * @param array $params          Additional variables passed to the template
     */
    public function getLabel($label = null, array $attributes = array(), array $parameters = array())
    {
        if (null !== $label) {
            $parameters['label'] = $label;
        }

        return $this->render('label', $attributes, $parameters);
    }

    protected function render($block, array $attributes = array(), array $parameters = array())
    {
        $this->setUpPlugins();

        return $this->theme->render($this->template, $block, array_replace(
            array('attr' => $attributes),
            $this->parameters,
            $parameters
        ));
    }
}