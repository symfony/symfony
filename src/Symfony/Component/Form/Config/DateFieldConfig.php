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
use Symfony\Component\Form\ChoiceList\PaddedChoiceList;
use Symfony\Component\Form\ChoiceList\MonthChoiceList;
use Symfony\Component\Form\Renderer\Plugin\DatePatternPlugin;
use Symfony\Component\Form\ValueTransformer\DateTimeToLocalizedStringTransformer;
use Symfony\Component\Form\ValueTransformer\DateTimeToArrayTransformer;
use Symfony\Component\Form\ValueTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\ValueTransformer\DateTimeToTimestampTransformer;
use Symfony\Component\Form\ValueTransformer\ReversedTransformer;

class DateFieldConfig extends AbstractFieldConfig
{
    public function configure(FieldInterface $field, array $options)
    {
        $formatter = new \IntlDateFormatter(
            \Locale::getDefault(),
            $options['format'],
            \IntlDateFormatter::NONE
        );

        if ($options['widget'] === 'text') {
            $field->setValueTransformer(new DateTimeToLocalizedStringTransformer(array(
                    'date_format' => $options['format'],
                    'time_format' => \IntlDateFormatter::NONE,
                    'input_timezone' => $options['data_timezone'],
                    'output_timezone' => $options['user_timezone'],
                )));
        } else {
            // Only pass a subset of the options to children
            $yearOptions = array(
                'choice_list' => new PaddedChoiceList(
                    $options['years'], 4, '0', STR_PAD_LEFT
                ),
            );
            $monthOptions = array(
                'choice_list' => new MonthChoiceList(
                    $formatter, $options['months']
                ),
            );
            $dayOptions = array(
                'choice_list' => new PaddedChoiceList(
                    $options['days'], 2, '0', STR_PAD_LEFT
                ),
            );

            $field->add($this->getInstance('choice', 'year', $yearOptions))
                ->add($this->getInstance('choice', 'month', $monthOptions))
                ->add($this->getInstance('choice', 'day', $dayOptions))
                ->setValueTransformer(new DateTimeToArrayTransformer(array(
                    'input_timezone' => $options['data_timezone'],
                    'output_timezone' => $options['user_timezone'],
                    'fields' => array('year', 'month', 'day'),
                )))
                ->addRendererPlugin(new DatePatternPlugin($formatter))
                // Don't modify \DateTime classes by reference, we treat
                // them like immutable value objects
                ->setModifyByReference(false);
        }

        if ($options['type'] === 'string') {
            $field->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToStringTransformer(array(
                    'input_timezone' => $options['data_timezone'],
                    'output_timezone' => $options['data_timezone'],
                    'format' => 'Y-m-d',
                ))
            ));
        } else if ($options['type'] === 'timestamp') {
            $field->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToTimestampTransformer(array(
                    'output_timezone' => $options['data_timezone'],
                    'input_timezone' => $options['data_timezone'],
                ))
            ));
        } else if ($options['type'] === 'array') {
            $field->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToArrayTransformer(array(
                    'input_timezone' => $options['data_timezone'],
                    'output_timezone' => $options['data_timezone'],
                    'fields' => array('year', 'month', 'day'),
                ))
            ));
        }

        $field->setRendererVar('widget', $options['widget']);
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'template' => 'date',
            'years' => range(date('Y') - 5, date('Y') + 5),
            'months' => range(1, 12),
            'days' => range(1, 31),
            'widget' => 'choice',
            'type' => 'datetime',
            'pattern' => null,
            'format' => \IntlDateFormatter::MEDIUM,
            'data_timezone' => date_default_timezone_get(),
            'user_timezone' => date_default_timezone_get(),
        );
    }

    public function getParent(array $options)
    {
        return $options['widget'] === 'text' ? 'field' : 'form';
    }

    public function getIdentifier()
    {
        return 'date';
    }
}