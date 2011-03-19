<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Type;

use Symfony\Component\Form\FieldBuilder;
use Symfony\Component\Form\ChoiceList\DefaultChoiceList;
use Symfony\Component\Form\EventListener\FixRadioInputListener;
use Symfony\Component\Form\Renderer\Plugin\ChoicePlugin;
use Symfony\Component\Form\Renderer\Plugin\SelectMultipleNamePlugin;
use Symfony\Component\Form\DataTransformer\ScalarToChoicesTransformer;
use Symfony\Component\Form\DataTransformer\ArrayToChoicesTransformer;

class ChoiceType extends AbstractType
{
    public function configure(FieldBuilder $builder, array $options)
    {
        if ($options['expanded']) {
            $choices = array_replace(
                $options['choice_list']->getPreferredChoices(),
                $options['choice_list']->getOtherChoices()
            );

            foreach ($choices as $choice => $value) {
                if ($options['multiple']) {
                    $builder->add((string)$choice, 'checkbox', array('value' => $choice));
                } else {
                    $builder->add((string)$choice, 'radio', array('value' => $choice));
                }
            }
        }

        $builder->addRendererPlugin(new ChoicePlugin($options['choice_list']))
            ->setRendererVar('multiple', $options['multiple'])
            ->setRendererVar('expanded', $options['expanded']);

        if ($options['multiple'] && $options['expanded']) {
            $builder->setClientTransformer(new ArrayToChoicesTransformer($options['choice_list']));
        }

        if (!$options['multiple'] && $options['expanded']) {
            $builder->setClientTransformer(new ScalarToChoicesTransformer($options['choice_list']));
            $builder->addEventSubscriber(new FixRadioInputListener(), 10);
        }

        if ($options['multiple'] && !$options['expanded']) {
            $builder->addRendererPlugin(new SelectMultipleNamePlugin());
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
            'csrf_protection' => false,
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

    public function getName()
    {
        return 'choice';
    }
}