<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\ValueTransformer\BooleanToStringTransformer;
use Symfony\Component\Form\Renderer\DefaultRenderer;
use Symfony\Component\Form\Renderer\Plugin\IdPlugin;
use Symfony\Component\Form\Renderer\Plugin\NamePlugin;
use Symfony\Component\Form\Renderer\Plugin\ValuePlugin;

class FormFactory
{
    private $theme;

    public function setTheme(ThemeInterface $theme)
    {
        $this->theme = $theme;
    }

    public function getTheme()
    {
        return $this->theme;
    }

    protected function getField($name, $template)
    {
        $field = new Field($name);

        return $field
            ->setRenderer(new DefaultRenderer($this->theme, $template))
            ->addRendererPlugin(new IdPlugin($field))
            ->addRendererPlugin(new NamePlugin($field));
    }

    public function getCheckboxField($name, $value = '1')
    {
        return $this->getField($name, 'checkbox')
            ->setValueTransformer(new BooleanToStringTransformer())
            ->addRendererPlugin(new ValuePlugin($value));
    }

    public function getRadioField($name)
    {
        $field = $this->getField($name, 'radio');

        return $field
            ->setValueTransformer(new BooleanToStringTransformer())
            ->addRendererPlugin(new ParentNamePlugin($field));
    }
}