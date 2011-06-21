<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\ReversedTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DataTransformerChain;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToArrayTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToTimestampTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\ArrayToPartsTransformer;

class DateTimeType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilder $builder, array $options)
    {
        // Only pass a subset of the options to children
        $dateOptions = array_intersect_key($options, array_flip(array(
            'years',
            'months',
            'days',
        )));
        $timeOptions = array_intersect_key($options, array_flip(array(
            'hours',
            'minutes',
            'seconds',
            'with_seconds',
        )));

        if (isset($options['date_widget'])) {
            $dateOptions['widget'] = $options['date_widget'];
        }
        if (isset($options['date_format'])) {
            $dateOptions['format'] = $options['date_format'];
        }

        $dateOptions['input'] = 'array';

        if (isset($options['time_widget'])) {
            $timeOptions['widget'] = $options['time_widget'];
        }

        $timeOptions['input'] = 'array';

        $parts = array('year', 'month', 'day', 'hour', 'minute');
        $timeParts = array('hour', 'minute');

        if ($options['with_seconds']) {
            $parts[] = 'second';
            $timeParts[] = 'second';
        }

        $builder
            ->appendClientTransformer(new DataTransformerChain(array(
                new DateTimeToArrayTransformer($options['data_timezone'], $options['user_timezone'], $parts),
                new ArrayToPartsTransformer(array(
                    'date' => array('year', 'month', 'day'),
                    'time' => $timeParts,
                )),
            )))
            ->add('date', 'date', $dateOptions)
            ->add('time', 'time', $timeOptions)
        ;

        if ($options['input'] === 'string') {
            $builder->appendNormTransformer(new ReversedTransformer(
                new DateTimeToStringTransformer($options['data_timezone'], $options['data_timezone'], 'Y-m-d H:i:s')
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
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOptions(array $options)
    {
        return array(
            'input'         => 'datetime',
            'with_seconds'  => false,
            'data_timezone' => null,
            'user_timezone' => null,
            // Don't modify \DateTime classes by reference, we treat
            // them like immutable value objects
            'by_reference'  => false,
            'date_widget'   => null,
            'date_format'   => null,
            'time_widget'   => null,
            /* Defaults for date field */
            'years'         => range(date('Y') - 5, date('Y') + 5),
            'months'        => range(1, 12),
            'days'          => range(1, 31),
            /* Defaults for time field */
            'hours'         => range(0, 23),
            'minutes'       => range(0, 59),
            'seconds'       => range(0, 59),
            'with_seconds'  => false,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getAllowedOptionValues(array $options)
    {
        return array(
            'input'         => array(
                'datetime',
                'string',
                'timestamp',
                'array',
            ),
            'date_widget'   => array(
                null, // inherit default from DateType
                'single_text',
                'text',
                'choice',
            ),
            'date_format'   => array(
                null, // inherit default from DateType
                \IntlDateFormatter::FULL,
                \IntlDateFormatter::LONG,
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::SHORT,
             ),
            'time_widget'   => array(
                null, // inherit default from TimeType
                'text',
                'choice',
            ),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'datetime';
    }
}
