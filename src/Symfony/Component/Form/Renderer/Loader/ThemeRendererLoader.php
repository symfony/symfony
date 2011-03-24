<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Renderer\Loader;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Renderer\ThemeRenderer;
use Symfony\Component\Form\Renderer\Theme\FormThemeFactoryInterface;

class ThemeRendererLoader implements RendererLoaderInterface
{
    private $name;

    private $themeFactory;

    public function __construct($name, FormThemeFactoryInterface $themeFactory)
    {
        $this->name = $name;
        $this->themeFactory = $themeFactory;
    }

    public function getRenderer($name, FormInterface $form)
    {
        if (!$name === $this->name) {
            throw new FormException(sprintf('Unknown renderer name "%s"', $name));
        }

        return new ThemeRenderer($form, $this->themeFactory);
    }

    public function hasRenderer($name)
    {
        return $this->name === $name;
    }
}