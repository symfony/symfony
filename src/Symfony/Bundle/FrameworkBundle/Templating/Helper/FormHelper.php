<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Templating\Helper;

use Symfony\Component\Templating\Helper\Helper;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Util\FormUtil;

/**
 *
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class FormHelper extends Helper
{
    protected $engine;

    protected $varStack;

    protected $context;

    protected $resources;

    protected $themes;

    protected $templates;

    /**
     * Constructor;
     *
     * @param EngineInterface $engine    The templating engine
     * @param array           $resources An array of theme name
     */
    public function __construct(EngineInterface $engine, array $resources)
    {
        $this->engine = $engine;
        $this->resources = $resources;
        $this->varStack = array();
        $this->context = array();
        $this->templates = array();
        $this->themes = array();
    }

    public function isChoiceGroup($label)
    {
        return FormUtil::isChoiceGroup($label);
    }

    public function isChoiceSelected(FormView $view, $choice)
    {
        return FormUtil::isChoiceSelected($choice, $view->get('value'));
    }

    /**
     * Sets a theme for a given view.
     *
     * The theme format is "<Bundle>:<Controller>".
     *
     * @param FormView     $view      A FormView instance
     * @param string|array $resources A theme or an array of theme
     */
    public function setTheme(FormView $view, $themes)
    {
        $this->themes[$view->get('id')] = (array) $themes;
        $this->templates = array();
    }

    /**
     * Renders the HTML enctype in the form tag, if necessary.
     *
     * Example usage templates:
     *
     *     <form action="..." method="post" <?php echo $view['form']->enctype() ?>>
     *
     * @param FormView $view  The view for which to render the encoding type
     *
     * @return string The html markup
     */
    public function enctype(FormView $view)
    {
        return $this->renderSection($view, 'enctype');
    }

    /**
     * Renders the HTML for a given view.
     *
     * Example usage:
     *
     *     <?php echo view['form']->widget() ?>
     *
     * You can pass options during the call:
     *
     *     <?php echo view['form']->widget(array('attr' => array('class' => 'foo'))) ?>
     *
     *     <?php echo view['form']->widget(array('separator' => '+++++)) ?>
     *
     * @param FormView $view      The view for which to render the widget
     * @param array    $variables Additional variables passed to the template
     *
     * @return string The html markup
     */
    public function widget(FormView $view, array $variables = array())
    {
        return trim($this->renderSection($view, 'widget', $variables));
    }

    /**
     * Renders the entire form field "row".
     *
     * @param FormView $view      The view for which to render the row
     * @param array    $variables Additional variables passed to the template
     *
     * @return string The html markup
     */
    public function row(FormView $view, array $variables = array())
    {
        return $this->renderSection($view, 'row', $variables);
    }

    /**
     * Renders the label of the given view.
     *
     * @param FormView $view      The view for which to render the label
     * @param string   $label     The label
     * @param array    $variables Additional variables passed to the template
     *
     * @return string The html markup
     */
    public function label(FormView $view, $label = null, array $variables = array())
    {
        if ($label !== null) {
            $variables += array('label' => $label);
        }

        return $this->renderSection($view, 'label', $variables);
    }

    /**
     * Renders the errors of the given view.
     *
     * @param FormView $view The view to render the errors for
     *
     * @return string The html markup
     */
    public function errors(FormView $view)
    {
        return $this->renderSection($view, 'errors');
    }

    /**
     * Renders views which have not already been rendered.
     *
     * @param FormView $view      The parent view
     * @param array    $variables An array of variables
     *
     * @return string The html markup
     */
    public function rest(FormView $view, array $variables = array())
    {
        return $this->renderSection($view, 'rest', $variables);
    }

    /**
     * Renders a template.
     *
     * 1. This function first looks for a block named "_<view id>_<section>",
     * 2. if such a block is not found the function will look for a block named
     *    "<type name>_<section>",
     * 3. the type name is recursively replaced by the parent type name until a
     *    corresponding block is found
     *
     * @param FormView  $view       The form view
     * @param string    $section    The section to render (i.e. 'row', 'widget', 'label', ...)
     * @param array     $variables  Additional variables
     *
     * @return string The html markup
     *
     * @throws FormException if no template block exists to render the given section of the view
     */
    protected function renderSection(FormView $view, $section, array $variables = array())
    {
        $mainTemplate = in_array($section, array('row', 'widget'));
        if ($mainTemplate && $view->isRendered()) {

                return '';
        }

        $template = null;

        $custom = '_'.$view->get('id');
        $rendering = $custom.$section;

        if (isset($this->varStack[$rendering])) {
            $typeIndex = $this->varStack[$rendering]['typeIndex'] - 1;
            $types = $this->varStack[$rendering]['types'];
            $variables = array_replace_recursive($this->varStack[$rendering]['variables'], $variables);
        } else {
            $types = $view->get('types');
            $types[] = $custom;
            $typeIndex = count($types) - 1;
            $variables = array_replace_recursive($view->all(), $variables);
            $this->varStack[$rendering]['types'] = $types;
        }

        $this->varStack[$rendering]['variables'] = $variables;

        do {
            $types[$typeIndex] .= '_'.$section;
            $template = $this->lookupTemplate($view, $types[$typeIndex]);

            if ($template) {

                $this->varStack[$rendering]['typeIndex'] = $typeIndex;

                $this->context[] = array(
                    'variables' => $variables,
                    'view'      => $view,
                );

                $html = $this->engine->render($template, $variables);

                array_pop($this->context);
                unset($this->varStack[$rendering]);

                if ($mainTemplate) {
                    $view->setRendered();
                }

                return $html;
            }
        }  while (--$typeIndex >= 0);

        throw new FormException(sprintf(
            'Unable to render the form as none of the following blocks exist: "%s".',
            implode('", "', array_reverse($types))
        ));
    }

    /**
     * Render a block from a form element.
     *
     * @param string $name
     * @param array  $variables Additional variables (those would override the current context)
     *
     * @throws FormException if the block is not found
     * @throws FormException if the method is called out of a form element (no context)
     */
    public function renderBlock($name, $variables = array())
    {
        if (0 == count($this->context)) {
            throw new FormException(sprintf('This method should only be called while rendering a form element.', $name));
        }

        $context = end($this->context);

        $template = $this->lookupTemplate($context['view'], $name);

        if (false === $template) {
            throw new FormException(sprintf('No block "%s" found while rendering the form.', $name));
        }

        $variables = array_replace_recursive($context['variables'], $variables);

        return $this->engine->render($template, $variables);
    }

    public function getName()
    {
        return 'form';
    }

    /**
     * Returns the name of the template to use to render the block
     *
     * @param FormView $view  The form view
     * @param string   $block The name of the block
     *
     * @return string|Boolean The template logical name or false when no template is found
     */
    protected function lookupTemplate(FormView $view, $block)
    {
        $file = $block.'.html.php';
        $id = $view->get('id');

        if (!isset($this->templates[$id][$block])) {
            $template = false;

            $themes = $view->hasParent() ? array() : $this->resources;

            if (isset($this->themes[$id])) {
                $themes = array_merge($themes, $this->themes[$id]);
            }

            for ($i = count($themes) - 1; $i >= 0; --$i) {
                if ($this->engine->exists($templateName = $themes[$i].':'.$file)) {
                    $template = $templateName;
                    break;
                }
            }

            if (false === $template && $view->hasParent()) {
                $template = $this->lookupTemplate($view->getParent(), $block);
            }

            $this->templates[$id][$block] = $template;
        }

        return $this->templates[$id][$block];
    }
}
