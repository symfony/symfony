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
use Symfony\Component\Form\ChoiceList\DefaultChoiceList;
use Symfony\Component\Form\DataProcessor\RadioToArrayConverter;
use Symfony\Component\Form\Renderer\DefaultRenderer;
use Symfony\Component\Form\Renderer\Theme\ThemeInterface;
use Symfony\Component\Form\Renderer\Plugin\IdPlugin;
use Symfony\Component\Form\Renderer\Plugin\NamePlugin;
use Symfony\Component\Form\Renderer\Plugin\ValuePlugin;
use Symfony\Component\Form\Renderer\Plugin\ChoicePlugin;
use Symfony\Component\Form\Renderer\Plugin\ParentNamePlugin;
use Symfony\Component\Form\ValueTransformer\ScalarToChoicesTransformer;

class FormFactory
{
    private $theme;

    public function __construct(ThemeInterface $theme)
    {
        $this->setTheme($theme);
    }

    public function setTheme(ThemeInterface $theme)
    {
        $this->theme = $theme;
    }

    public function getTheme()
    {
        return $this->theme;
    }

    protected function getField($key, $template)
    {
        $field = new Field($key);

        return $field
            ->setRenderer(new DefaultRenderer($this->theme, $template))
            ->addRendererPlugin(new IdPlugin($field))
            ->addRendererPlugin(new NamePlugin($field));
    }

    protected function getForm($key, $template)
    {
        $field = new Form($key);

        return $field
            ->setRenderer(new DefaultRenderer($this->theme, $template))
            ->addRendererPlugin(new IdPlugin($field))
            ->addRendererPlugin(new NamePlugin($field));
    }

    public function getCheckboxField($key, array $options = array())
    {
        $options = array_replace(array(
            'value' => '1',
        ), $options);

        return $this->getField($key, 'checkbox')
            ->setValueTransformer(new BooleanToStringTransformer())
            ->addRendererPlugin(new ValuePlugin($options['value']));
    }

    public function getRadioField($key, array $options = array())
    {
        $options = array_replace(array(
            'value' => null,
        ), $options);

        $field = $this->getField($key, 'radio');

        return $field
            ->setValueTransformer(new BooleanToStringTransformer())
            ->addRendererPlugin(new ParentNamePlugin($field))
            ->addRendererPlugin(new ValuePlugin($options['value']));
    }

    public function getChoiceField($key, array $options = array())
    {
        $options = array_replace(array(
            'choices' => array(),
            'preferred_choices' => array(),
            'multiple' => false,
            'expanded' => false,
        ), $options);

        $choiceList = new DefaultChoiceList(
            $options['choices'],
            $options['preferred_choices']
        );

        if (!$options['expanded']) {
            $field = $this->getField($key, 'choice');
        } else {
            $field = $this->getForm($key, 'choice_expanded');
            $choices = array_merge($choiceList->getPreferredChoices(), $choiceList->getOtherChoices());

            foreach ($choices as $choice => $value) {
                if ($options['multiple']) {
                    $field->add($this->getCheckboxField($choice, array(
                        'value' => $choice,
                    )));
                } else {
                    $field->add($this->getRadioField($choice, array(
                        'value' => $choice,
                    )));
                }
            }
        }

        $field->addRendererPlugin(new ChoicePlugin($choiceList));

        if ($options['multiple'] && $options['expanded']) {
            $field->setValueTransformer(new ArrayToChoicesTransformer($choiceList));
        }

        if (!$options['multiple'] && $options['expanded']) {
            $field->setValueTransformer(new ScalarToChoicesTransformer($choiceList));
            $field->setDataPreprocessor(new RadioToArrayConverter());
        }

        if ($options['multiple'] && !$options['expanded']) {
            $field->addRendererPlugin(new SelectMultipleNamePlugin($field));
        }

        return $field;
    }
}