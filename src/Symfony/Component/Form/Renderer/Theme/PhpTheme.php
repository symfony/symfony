<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Renderer\Theme;

use Symfony\Component\Form\Form;
use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Templating\PhpEngine;

/**
 * Renders a Form using the PHP Templating Engine.
 *
 * Each field is rendered as slot of a template.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class PhpTheme implements ThemeInterface
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
    private $template;

    /**
     * @param PhpEngine $engine
     */
    public function __construct(PhpEngine $engine, $template = null)
    {
        $this->engine = $engine;
        $this->template = $template;
    }

    public function render($field, $section, array $parameters)
    {
        if ($template = $this->lookupTemplate($field."_".$section)) {
            return $this->engine->render($template, $parameters);
        } else if ($template = $this->lookupTemplate($section)) {
            return $this->engine->render($template, $parameters);
        } else {
            throw new FormException(sprintf('The form theme is missing the "%s" template file.', $section));
        }
    }

    protected function lookupTemplate($templateName)
    {
        if (isset(self::$cache[$templateName])) {
            return self::$cache[$templateName];
        }

        $template = (($this->template) ? ($this->template.":") : "") . $templateName.'.html.php';
        if (!$this->engine->exists($template)) {
            $template = false;
        }

        self::$cache[$templateName] = $template;

        return $template;
    }
}
