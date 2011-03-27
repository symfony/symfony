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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Exception\FormException;

class TwigTheme implements FormThemeInterface
{
    private $environment;
    private $templates;
    private $templatesByBlock;

    public function __construct(\Twig_Environment $environment, $templates)
    {
        if (empty($templates)) {
            $templates = array();
        } else if (!is_array($templates)) {
            // Don't use type casting, because then objects (Twig_Template)
            // are converted to arrays
            $templates = array($templates);
        }

        $this->environment = $environment;
        $this->templates = array();

        foreach ($templates as $template) {
            // Remove duplicate template names
            if (!is_string($template)) {
                $this->templates[] = $template;
            } else if (!isset($this->templates[$template])) {
                $this->templates[$template] = $template;
            }
        }
    }

    private function initialize()
    {
        if (!$this->templatesByBlock) {
            $this->templatesByBlock = array();

            foreach ($this->templates as $key => $template) {
                if (!$template instanceof \Twig_Template) {
                    $this->templates[$key] = $template = $this->environment->loadTemplate($template);
                }

                foreach ($this->getBlockNames($template) as $blockName) {
                    $this->templatesByBlock[$blockName] = $template;
                }
            }
        }
    }

    private function getBlockNames(\Twig_Template $template)
    {
        $names = $template->getBlockNames();
        $parent = $template;

        while (false !== $parent = $parent->getParent(array())) {
            $names = array_merge($names, $parent->getBlockNames());
        }

        return array_unique($names);
    }

    public function render($blocks, $section, array $parameters)
    {
        $this->initialize();

        $blocks = (array)$blocks;

        foreach ($blocks as $block) {
            $blockName = $block.'__'.$section;

            if (isset($this->templatesByBlock[$blockName])) {
                return $this->templatesByBlock[$blockName]->renderBlock($blockName, $parameters);
            }
        }

        $blocks = array_map(function ($block) use ($section) {
            return $block.'__'.$section;
        }, $blocks);

        throw new FormException(sprintf('The form theme is missing the "%s" blocks', implode('", "', $blocks)));
    }
}