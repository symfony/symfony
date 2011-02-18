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

class TwigTheme implements ThemeInterface
{
    private $environment;
    private $template;
    private $blocks;

    public function __construct(\Twig_Environment $environment, $template)
    {
        $this->environment = $environment;
        $this->template = $template;
    }

    private function initialize()
    {
        if (!$this->blocks) {
            $this->blocks = array();

            if (!$this->template instanceof \Twig_Template) {
                $this->template = $this->environment->loadTemplate($this->template);
            }

            foreach ($this->getBlockNames($this->template) as $blockName) {
                $this->blocks[$blockName] = true;
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

    public function render($template, $block, array $parameters)
    {
        $this->initialize();

        if (isset($this->blocks[$template.'__'.$block])) {
            $blockName = $template.'__'.$block;
        } else if (isset($this->blocks[$block])) {
            $blockName = $block;
        } else {
            throw new FormException(sprintf('The form theme is missing the "%s" block', $block));
        }

        return $this->template->renderBlock($blockName, $parameters);
    }
}