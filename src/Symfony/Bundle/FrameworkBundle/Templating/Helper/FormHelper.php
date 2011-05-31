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

/**
 *
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class FormHelper extends Helper
{
    static protected $cache = array();

    protected $engine;

    protected $varStack;

    protected $viewStack = array();

    public function __construct(EngineInterface $engine)
    {
        $this->engine = $engine;
        $this->varStack = new \SplObjectStorage();
    }

    public function attributes()
    {
        $html = '';
        $attr = array();

        if (count($this->viewStack) > 0) {
            $view = end($this->viewStack);
            $vars = $this->varStack[$view];

            if (isset($vars['attr'])) {
                $attr = $vars['attr'];
            }

            if (isset($vars['id'])) {
                $attr['id'] = $vars['id'];
            }
        }

        foreach ($attr as $k => $v) {
            $html .= ' '.$this->engine->escape($k).'="'.$this->engine->escape($v).'"';
        }

        return $html;
    }

    /**
     * Renders the HTML enctype in the form tag, if necessary
     *
     * @param FormView $view The view for which to render the encoding type
     *
     * @return string
     */
    public function enctype(FormView $view)
    {
        return $this->renderSection($view, 'enctype');
    }

    /**
     * Renders the HTML for a given view
     *
     * @param FormView $view      The view to render
     * @param array    $variables Additional variables passed to the template
     *
     * @return string
     */
    public function widget(FormView $view, array $variables = array())
    {
        return trim($this->renderSection($view, 'widget', $variables));
    }

    /**
     * Renders the entire form field "row".
     *
     * @param FormView $view      The view to render the row for
     * @param array    $variables Additional variables passed to the template
     *
     * @return string
     */
    public function row(FormView $view, array $variables = array())
    {
        return $this->renderSection($view, 'row', $variables);
    }

    /**
     * Renders the label of the given view
     *
     * @param FormView $view  The view to render the label for
     * @param string   $label Label name
     *
     * @return string
     */
    public function label(FormView $view, $label = null, array $variables = array())
    {
        if ($label !== null) {
            $variables += array('label' => $label);
        }

        return $this->renderSection($view, 'label', $variables);
    }

    /**
     * Renders the errors of the given view
     *
     * @param FormView $view The view to render the errors for
     *
     * @return string
     */
    public function errors(FormView $view)
    {
        return $this->renderSection($view, 'errors');
    }

    public function rest(FormView $view, array $variables = array())
    {
        return $this->renderSection($view, 'rest', $variables);
    }

    protected function renderSection(FormView $view, $section, array $variables = array())
    {
        $template = null;
        $blocks = $view->get('types');
        array_unshift($blocks, '_'.$view->get('id'));

        foreach ($blocks as &$block) {
            $block .= '_'.$section;
            if ($view->isFieldRendered($block)) {
                return;
            }

            if ($template = $this->lookupTemplate($block)) {
                break;
            }
        }

        if (!$template) {
            throw new FormException(sprintf('Unable to render form as none of the following blocks exist: "%s".', implode('", "', $blocks)));
        }

        if ('widget' === $section || 'row' === $section) {
            $view->setRendered();
            $view->setFieldRendered($block);
        }

        return $this->render($view, $template, $variables);
    }

    public function render(FormView $view, $template, array $variables = array())
    {
        $this->varStack[$view] = array_replace(
            $view->all(),
            isset($this->varStack[$view]) ? $this->varStack[$view] : array(),
            $variables
        );

        $this->viewStack[] = $view;

        $html = $this->engine->render($template, $this->varStack[$view]);

        array_pop($this->viewStack);
        unset($this->varStack[$view]);

        return $html;
    }

    protected function lookupTemplate($templateName)
    {
        if (isset(self::$cache[$templateName])) {
            return self::$cache[$templateName];
        }

        $template = 'FrameworkBundle:Form:'.$templateName.'.html.php';
        if (!$this->engine->exists($template)) {
            $template = false;
        }

        self::$cache[$templateName] = $template;

        return $template;
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'form';
    }
}
