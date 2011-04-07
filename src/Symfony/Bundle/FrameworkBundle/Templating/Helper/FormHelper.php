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
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\Form\TemplateContext;
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

    public function __construct(EngineInterface $engine)
    {
        $this->engine = $engine;
    }

    public function attributes(array $attribs)
    {
        $html = '';
        foreach ($attribs as $k => $v) {
            $html .= $this->engine->escape($k).'="'.$this->engine->escape($v).'" ';
        }

        return $html;
    }

    public function enctype(TemplateContext $context)
    {
        return $this->renderTemplate($context, 'enctype');
    }

    public function widget(TemplateContext $context, array $parameters = array(), $template = null)
    {
        return trim($this->renderTemplate($context, 'widget', $parameters, $template));
    }

    /**
     * Renders the entire form field "row".
     *
     * @param  FieldInterface $field
     * @return string
     */
    public function row(TemplateContext $context, $template = null)
    {
        return $this->renderTemplate($context, 'row', array(), $template);
    }

    public function label(TemplateContext $context, $label = null, array $parameters = array(), $template = null)
    {
        return $this->renderTemplate($context, 'label', null === $label ? array() : array('label' => $label));
    }

    public function errors(TemplateContext $context, array $parameters = array(), $template = null)
    {
        return $this->renderTemplate($context, 'errors', array(), $template);
    }

    public function rest(TemplateContext $context, array $parameters = array(), $template = null)
    {
        return $this->renderTemplate($context, 'rest', array(), $template);
    }

    protected function renderTemplate(TemplateContext $context, $section, array $variables = array(), array $resources = null)
    {
        $blocks = $context->get('types');
        foreach ($blocks as &$block) {
            $block = $block.'_'.$section;

            if ($template = $this->lookupTemplate($block)) {
                if ('widget' === $section) {
                    $context->set('is_rendered', true);
                }

                return $this->engine->render($template, array_merge($context->all(), $variables));
            }
        }

        throw new FormException(sprintf('Unable to render form as none of the following blocks exist: "%s".', implode('", "', $blocks)));
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
