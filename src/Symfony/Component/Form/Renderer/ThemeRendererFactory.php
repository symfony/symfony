<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Renderer;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Renderer\ThemeRenderer;
use Symfony\Component\Form\Renderer\Theme\ThemeFactoryInterface;

class ThemeRendererFactory implements RendererFactoryInterface
{
    private $themeFactory;

    private $defaultTemplate;

    public function __construct(ThemeFactoryInterface $themeFactory, $defaultTemplate = null)
    {
        $this->themeFactory = $themeFactory;
        $this->defaultTemplate = $defaultTemplate;
    }

    public function create(FormInterface $form, ThemeRenderer $parent = null)
    {
        $renderer = new ThemeRenderer($this->themeFactory);

        if (!$parent) {
            $renderer->setTemplate($this->defaultTemplate);
        } else {
            $renderer->setParent($parent);
        }

        $types = (array)$form->getTypes();
        $children = array();

        foreach ($types as $type) {
            $renderer->setBlock($type->getName());
            $type->buildRenderer($renderer, $form);
        }

        foreach ($form as $key => $child) {
            $children[$key] = $this->create($child, $renderer);
        }

        $renderer->setChildren($children);

        foreach ($types as $type) {
            $type->buildRendererBottomUp($renderer, $form);
        }

        return $renderer;
    }
}