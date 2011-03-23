<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Renderer\ThemeEngine;

use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Exception\FormException;

class TwigThemeEngine implements FormThemeEngineInterface
{
    private $environment;
    private $themes;
    private $themesByBlock;

    public function __construct(\Twig_Environment $environment, $themes)
    {
        $this->environment = $environment;
        $this->themes = (array)$themes;
    }

    private function initialize()
    {
        if (!$this->themesByBlock) {
            $this->themesByBlock = array();

            foreach ($this->themes as $theme) {
                $theme = $this->environment->loadTemplate($theme);

                foreach ($this->getBlockNames($theme) as $blockName) {
                    $this->themesByBlock[$blockName] = $theme;
                }
            }
        }
    }

    private function getBlockNames(\Twig_Template $theme)
    {
        $names = $theme->getBlockNames();
        $parent = $theme;

        while (false !== $parent = $parent->getParent(array())) {
            $names = array_merge($names, $parent->getBlockNames());
        }

        return array_unique($names);
    }

    public function render($field, $section, array $parameters)
    {
        $this->initialize();

        if (isset($this->themesByBlock[$field.'__'.$section])) {
            $blockName = $field.'__'.$section;
        } else if (isset($this->themesByBlock[$section])) {
            $blockName = $section;
        } else {
            throw new FormException(sprintf('The form theme is missing the "%s" block', $section));
        }

        return $this->themesByBlock[$blockName]->renderBlock($blockName, $parameters);
    }
}