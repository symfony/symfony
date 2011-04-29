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

    protected $varStack = array();

    public function __construct(EngineInterface $engine)
    {
        $this->engine = $engine;
    }

    public function attributes()
    {
        $html = '';
        $attr = array();

        if (count($this->varStack) > 0) {
            $vars = end($this->varStack);

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

    public function enctype(FormView $view)
    {
        return $this->renderSection($view, 'enctype');
    }

    public function widget(FormView $view, array $variables = array())
    {
        return trim($this->renderSection($view, 'widget', $variables));
    }

    /**
     * Renders the entire form field "row".
     *
     * @param FormView $view
     * @param array    $variables
     *
     * @return string
     */
    public function row(FormView $view, array $variables = array())
    {
        return $this->renderSection($view, 'row', $variables);
    }

    public function label(FormView $view, $label = null)
    {
        return $this->renderSection($view, 'label', null === $label ? array() : array('label' => $label));
    }

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
        if ('widget' === $section || 'row' === $section) {
            array_unshift($blocks, '_'.$view->get('id'));
        }
        foreach ($blocks as &$block) {
            $block = $block.'_'.$section;
            $template = $this->lookupTemplate($block);

            if ($template) {
                break;
            }
        }

        if (!$template) {
            throw new FormException(sprintf('Unable to render form as none of the following blocks exist: "%s".', implode('", "', $blocks)));
        }

        if ('widget' === $section || 'row' === $section) {
            $view->setRendered();
        }

        return $this->render($template, array_merge($view->all(), $variables));
    }

    public function render($template, array $variables = array())
    {
        array_push($this->varStack, array_merge(
            count($this->varStack) > 0 ? end($this->varStack) : array(),
            $variables
        ));

        $html = $this->engine->render($template, end($this->varStack));

        array_pop($this->varStack);

        return $html;
    }

    protected function lookupTemplate($templateName)
    {
        if (isset(self::$cache[$templateName])) {
            return self::$cache[$templateName];
        }

        $template = $templateName.'.html.php';
/*
        if ($this->templateDir) {
            $template = $this->templateDir.':'.$template;
        }
*/
$template = 'FrameworkBundle:Form:'.$template;
        if (!$this->engine->exists($template)) {
            $template = false;
        }

        self::$cache[$templateName] = $template;

        return $template;
    }

    public function getName()
    {
        return 'form';
    }
}
