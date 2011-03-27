<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Form;

use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Renderer\Theme\ThemeInterface;
use Symfony\Component\Templating\PhpEngine;

/**
 * Renders a Form using the PHP Templating Engine.
 *
 * Each field is rendered as slot of a template.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class PhpEngineTheme implements ThemeInterface
{
    /**
     * @var array
     */
    static protected $cache = array();

    /**
     * @var PhpEngine
     */
    private $engine;

    /**
     * @var string
     */
    private $templateDir;

    /**
     * @param PhpEngine $engine
     */
    public function __construct(PhpEngine $engine, $templateDir = null)
    {
        $this->engine = $engine;
        $this->templateDir = $templateDir;
    }

    public function render($blocks, $section, array $parameters)
    {
        $blocks = (array)$blocks;

        foreach ($blocks as &$block) {
            $block = $block.'_'.$section;

            if ($template = $this->lookupTemplate($block)) {
                return $this->engine->render($template, $parameters);
            }
        }

        throw new FormException(sprintf('The form theme is missing the "%s" template files', implode('", "', $blocks)));
    }

    protected function lookupTemplate($templateName)
    {
        if (isset(self::$cache[$templateName])) {
            return self::$cache[$templateName];
        }

        $template = $templateName.'.html.php';

        if ($this->templateDir) {
            $template = $this->templateDir . ':' . $template;
        }

        if (!$this->engine->exists($template)) {
            $template = false;
        }

        self::$cache[$templateName] = $template;

        return $template;
    }

    public function attributes(array $attribs)
    {
        $html = '';
        foreach ($attribs as $k => $v) {
            $html .= $this->engine->escape($k) . '="' . $this->engine->escape($v) .'" ';
        }
        return $html;
    }
}
