<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Config;

use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\ChoiceList\DefaultChoiceList;
use Symfony\Component\Form\Filter\RadioInputFilter;
use Symfony\Component\Form\Renderer\Plugin\ChoicePlugin;
use Symfony\Component\Form\Renderer\Plugin\SelectMultipleNamePlugin;
use Symfony\Component\Form\ValueTransformer\ScalarToChoicesTransformer;
use Symfony\Component\Form\ValueTransformer\ArrayToChoicesTransformer;

class ChoiceFieldConfig extends AbstractFieldConfig
{
    public function configure(FieldInterface $field, array $options)
    {
        if ($options['expanded']) {
            $choices = array_replace(
                $options['choice_list']->getPreferredChoices(),
                $options['choice_list']->getOtherChoices()
            );

            foreach ($choices as $choice => $value) {
                if ($options['multiple']) {
                    $field->add($this->getInstance('checkbox', $choice, array(
                        'value' => $choice,
                    )));
                } else {
                    $field->add($this->getInstance('radio', $choice, array(
                        'value' => $choice,
                    )));
                }
            }
        }

        $field->addRendererPlugin(new ChoicePlugin($options['choice_list']))
            ->setRendererVar('multiple', $options['multiple'])
            ->setRendererVar('expanded', $options['expanded']);

        if ($options['multiple'] && $options['expanded']) {
            $field->setValueTransformer(new ArrayToChoicesTransformer($options['choice_list']));
        }

        if (!$options['multiple'] && $options['expanded']) {
            $field->setValueTransformer(new ScalarToChoicesTransformer($options['choice_list']));
            $field->prependFilter(new RadioInputFilter());
        }

        if ($options['multiple'] && !$options['expanded']) {
            $field->addRendererPlugin(new SelectMultipleNamePlugin($field));
        }
    }

    public function getDefaultOptions(array $options)
    {
        $defaultOptions = array(
            'template' => 'choice',
            'multiple' => false,
            'expanded' => false,
            'choices' => array(),
            'preferred_choices' => array(),
        );

        $options = array_replace($defaultOptions, $options);

        if (!isset($options['choice_list'])) {
            $defaultOptions['choice_list'] = new DefaultChoiceList(
                $options['choices'],
                $options['preferred_choices']
            );
        }

        return $defaultOptions;
    }

    public function getParent(array $options)
    {
        return $options['expanded'] ? 'form' : 'field';
    }

    public function getIdentifier()
    {
        return 'choice';
    }
}