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
    private $templates;
    private $templatesByBlock;

    public function __construct(\Twig_Environment $environment, $templates)
    {
        $this->environment = $environment;
        $this->templates = (array)$templates;
    }

    private function initialize()
    {
        if (!$this->templatesByBlock) {
            $this->templatesByBlock = array();

            foreach ($this->templates as $template) {
                $template = $this->environment->loadTemplate($template);

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

    public function render($field, $section, array $parameters)
    {
        $this->initialize();

        if (isset($this->templatesByBlock[$field.'__'.$section])) {
            $blockName = $field.'__'.$section;
        } else if (isset($this->templatesByBlock[$section])) {
            $blockName = $section;
        } else {
            throw new FormException(sprintf('The form theme is missing the "%s" block', $section));
        }

        return $this->templatesByBlock[$blockName]->renderBlock($blockName, $parameters);
    }
}