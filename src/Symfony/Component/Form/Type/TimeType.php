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

use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\ChoiceList\PaddedChoiceList;
use Symfony\Component\Form\DataTransformer\ReversedTransformer;
use Symfony\Component\Form\DataTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\DataTransformer\DateTimeToTimestampTransformer;
use Symfony\Component\Form\DataTransformer\DateTimeToArrayTransformer;

class TimeType extends AbstractType
{
    public function configure(FormBuilder $builder, array $options)
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
            $builder->setNormTransformer(new ReversedTransformer(
                new DateTimeToStringTransformer(array(
                    'format' => 'H:i:s',
                    'input_timezone' => $options['data_timezone'],
                    'output_timezone' => $options['data_timezone'],
                ))
            ));
        } else if ($options['input'] === 'timestamp') {
            $builder->setNormTransformer(new ReversedTransformer(
                new DateTimeToTimestampTransformer(array(
                    'input_timezone' => $options['data_timezone'],
                    'output_timezone' => $options['data_timezone'],
                ))
            ));
        } else if ($options['input'] === 'array') {
            $builder->setNormTransformer(new ReversedTransformer(
                new DateTimeToArrayTransformer(array(
                    'input_timezone' => $options['data_timezone'],
                    'output_timezone' => $options['data_timezone'],
                    'fields' => $parts,
                ))
            ));
        }

        $builder
            ->setClientTransformer(new DateTimeToArrayTransformer(array(
                'input_timezone' => $options['data_timezone'],
                'output_timezone' => $options['user_timezone'],
                // if the field is rendered as choice field, the values should be trimmed
                // of trailing zeros to render the selected choices correctly
                'pad' => $options['widget'] === 'text',
                'fields' => $parts,
            )))
            ->setRendererVar('widget', $options['widget'])
            ->setRendererVar('with_seconds', $options['with_seconds']);
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'template' => 'time',
            'hours' => range(0, 23),
            'minutes' => range(0, 59),
            'seconds' => range(0, 59),
            'widget' => 'choice',
            'input' => 'datetime',
            'with_seconds' => false,
            'pattern' => null,
            'data_timezone' => date_default_timezone_get(),
            'user_timezone' => date_default_timezone_get(),
            'csrf_protection' => false,
            // Don't modify \DateTime classes by reference, we treat
            // them like immutable value objects
            'by_reference' => false,
        );
    }

    public function getParent(array $options)
    {
        return 'form';
    }

    public function getName()
    {
        return 'time';
    }
}