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

use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * Creates TwigTheme instances
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class TwigThemeFactory implements ThemeFactoryInterface
{
    /**
     * @var Twig_Environment
     */
    private $environment;

    /**
     * @var array
     */
    private $fallbackTemplates;

    public function __construct(\Twig_Environment $environment, $fallbackTemplates = null)
    {
        if (empty($fallbackTemplates)) {
            $fallbackTemplates = array();
        } else if (!is_array($fallbackTemplates)) {
            // Don't use type casting, because then objects (Twig_Template)
            // are converted to arrays
            $fallbackTemplates = array($fallbackTemplates);
        }

        $this->environment = $environment;
        $this->fallbackTemplates = $fallbackTemplates;
    }

    /**
     * @see Symfony\Component\Form\Renderer\Theme\ThemeFactoryInterface::create()
     */
    public function create($template = null)
    {
        if (null !== $template && !is_string($template) && !$template instanceof \Twig_Template) {
            throw new UnexpectedTypeException($template, 'string or Twig_Template');
        }

        $templates = $template
            ? array_merge($this->fallbackTemplates, array($template))
            : $this->fallbackTemplates;

        if (count($templates) === 0) {
            throw new FormException('Twig themes either need default templates or templates passed during creation');
        }

        return new TwigTheme($this->environment, $templates);
    }
}