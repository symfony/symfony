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
    static protected $cache = array();

    protected $engine;

    protected $varStack;

    protected $viewStack = array();

    public function __construct(EngineInterface $engine)
    {
        $this->engine = $engine;
        $this->varStack = new \SplObjectStorage();
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
     * Renders the attributes for the current view.
     *
     * @param Boolean $includeId Whether to render the id attribute
     *
     * @return string The HTML markup
     */
    public function attributes($includeId = true)
    {
        $html = '';
        $attr = array();

        if (count($this->viewStack) > 0) {
            $view = end($this->viewStack);
            $vars = $this->varStack[$view];

            if (isset($vars['attr'])) {
                $attr = $vars['attr'];
            }

            if (true === $includeId && isset($vars['id'])) {
                $attr['id'] = $vars['id'];
            }
        }

        foreach ($attr as $k => $v) {
            $html .= ' '.$this->engine->escape($k).'="'.$this->engine->escape($v).'"';
        }

        return $html;
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
        $types = $view->get('types');
        $types[] = '_'.$view->get('proto_id', $view->get('id'));

        for ($i = count($types) - 1; $i >= 0; $i--) {
            $types[$i] .= '_'.$section;
            $template = $this->lookupTemplate($types[$i]);

            if ($template) {
                $html = $this->render($view, $template, $variables);

                if ($mainTemplate) {
                    $view->setRendered();
                }

                return $html;
            }
        }

        throw new FormException(sprintf('Unable to render form as none of the following blocks exist: "%s".', implode('", "', $types)));
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

    /**
     * Returns the name of the template to use to render the block
     *
     * @param string $blockName The name of the block
     *
     * @return string|Boolean The template logical name or false when no template is found
     */
    protected function lookupTemplate($blockName)
    {
        if (isset(self::$cache[$blockName])) {
            return self::$cache[$blockName];
        }

        $template = $blockName.'.html.php';
/*
        if ($this->templateDir) {
            $template = $this->templateDir.':'.$template;
        }
*/
        $template = 'FrameworkBundle:Form:'.$template;
        if (!$this->engine->exists($template)) {
            $template = false;
        }

        self::$cache[$blockName] = $template;

        return $template;
    }

    public function getName()
    {
        return 'form';
    }
}
