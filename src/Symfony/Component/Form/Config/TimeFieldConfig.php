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

use Symfony\Component\Form\FieldBuilder;
use Symfony\Component\Form\ChoiceList\PaddedChoiceList;
use Symfony\Component\Form\DataTransformer\ReversedTransformer;
use Symfony\Component\Form\DataTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\DataTransformer\DateTimeToTimestampTransformer;
use Symfony\Component\Form\DataTransformer\DateTimeToArrayTransformer;

class TimeFieldConfig extends AbstractFieldConfig
{
    public function configure(FieldBuilder $builder, array $options)
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

        $builder->add($options['widget'], 'hour', $hourOptions)
            ->add($options['widget'], 'minute', $minuteOptions);

        if ($options['with_seconds']) {
            $parts[] = 'second';
            $builder->add($options['widget'], 'second', $secondOptions);
        }

        if ($options['type'] == 'string') {
            $builder->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToStringTransformer(array(
                    'format' => 'H:i:s',
                    'input_timezone' => $options['data_timezone'],
                    'output_timezone' => $options['data_timezone'],
                ))
            ));
        } else if ($options['type'] == 'timestamp') {
            $builder->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToTimestampTransformer(array(
                    'input_timezone' => $options['data_timezone'],
                    'output_timezone' => $options['data_timezone'],
                ))
            ));
        } else if ($options['type'] === 'array') {
            $builder->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToArrayTransformer(array(
                    'input_timezone' => $options['data_timezone'],
                    'output_timezone' => $options['data_timezone'],
                    'fields' => $parts,
                ))
            ));
        }

        $builder
            ->setDataTransformer(new DateTimeToArrayTransformer(array(
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
            'type' => 'datetime',
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

    public function getIdentifier()
    {
        return 'time';
    }
}