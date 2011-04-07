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

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\ChoiceList\PaddedChoiceList;
use Symfony\Component\Form\DataTransformer\ReversedTransformer;
use Symfony\Component\Form\DataTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\DataTransformer\DateTimeToTimestampTransformer;
use Symfony\Component\Form\DataTransformer\DateTimeToArrayTransformer;
use Symfony\Component\Form\TemplateContext;

class TimeType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $hourOptions = $minuteOptions = $secondOptions = array();
        $child = $options['widget'] === 'text' ? 'text' : 'choice';
        $parts = array('hour', 'minute');

        if ($options['widget'] === 'choice') {
            $hourOptions['choice_list'] =  new PaddedChoiceList(
                $options['hours'], 2, '0', STR_PAD_LEFT
            );
            $minuteOptions['choice_list'] = new PaddedChoiceList(
                $options['minutes'], 2, '0', STR_PAD_LEFT
            );

            if ($options['with_seconds']) {
                $secondOptions['choice_list'] = new PaddedChoiceList(
                    $options['seconds'], 2, '0', STR_PAD_LEFT
                );
            }
        }

        $builder->add('hour', $options['widget'], $hourOptions)
            ->add('minute', $options['widget'], $minuteOptions);

        if ($options['with_seconds']) {
            $parts[] = 'second';
            $builder->add('second', $options['widget'], $secondOptions);
        }

        if ($options['input'] === 'string') {
            $builder->appendNormTransformer(new ReversedTransformer(
                new DateTimeToStringTransformer($options['data_timezone'], $options['data_timezone'], 'H:i:s')
            ));
        } else if ($options['input'] === 'timestamp') {
            $builder->appendNormTransformer(new ReversedTransformer(
                new DateTimeToTimestampTransformer($options['data_timezone'], $options['data_timezone'])
            ));
        } else if ($options['input'] === 'array') {
            $builder->appendNormTransformer(new ReversedTransformer(
                new DateTimeToArrayTransformer($options['data_timezone'], $options['data_timezone'], $parts)
            ));
        }

        $builder
            ->appendClientTransformer(new DateTimeToArrayTransformer($options['data_timezone'], $options['user_timezone'], $parts, $options['widget'] === 'text'))
            ->setAttribute('widget', $options['widget'])
            ->setAttribute('with_seconds', $options['with_seconds']);
    }

    public function buildVariables(TemplateContext $variables, FormInterface $form)
    {
        $variables->set('widget', $form->getAttribute('widget'));
        $variables->set('with_seconds', $form->getAttribute('with_seconds'));
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'hours' => range(0, 23),
            'minutes' => range(0, 59),
            'seconds' => range(0, 59),
            'widget' => 'choice',
            'input' => 'datetime',
            'with_seconds' => false,
            'pattern' => null,
            'data_timezone' => null,
            'user_timezone' => null,
            'csrf_protection' => false,
            // Don't modify \DateTime classes by reference, we treat
            // them like immutable value objects
            'by_reference' => false,
        );
    }

    public function getName()
    {
        return 'time';
    }
}