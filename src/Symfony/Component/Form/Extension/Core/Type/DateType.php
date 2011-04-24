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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Extension\Core\ChoiceList\PaddedChoiceList;
use Symfony\Component\Form\Extension\Core\ChoiceList\MonthChoiceList;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToLocalizedStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToArrayTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToTimestampTransformer;
use Symfony\Component\Form\ReversedTransformer;

class DateType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $formatter = new \IntlDateFormatter(
            \Locale::getDefault(),
            $options['format'],
            \IntlDateFormatter::NONE,
            \DateTimeZone::UTC
        );

        if ($options['widget'] === 'text') {
            $builder->appendClientTransformer(new DateTimeToLocalizedStringTransformer($options['data_timezone'], $options['user_timezone'], $options['format'], \IntlDateFormatter::NONE));
        } elseif ($options['widget'] == 'choice') {
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

            $builder->add('year', 'choice', $yearOptions)
                ->add('month', 'choice', $monthOptions)
                ->add('day', 'choice', $dayOptions)
                ->appendClientTransformer(new DateTimeToArrayTransformer($options['data_timezone'], $options['user_timezone'], array('year', 'month', 'day')));
        } else {
            throw new FormException('The "widget" option must be set to either "text" or "choice".');
        }

        if ($options['input'] === 'string') {
            $builder->appendNormTransformer(new ReversedTransformer(
                new DateTimeToStringTransformer($options['data_timezone'], $options['data_timezone'], 'Y-m-d')
            ));
        } else if ($options['input'] === 'timestamp') {
            $builder->appendNormTransformer(new ReversedTransformer(
                new DateTimeToTimestampTransformer($options['data_timezone'], $options['data_timezone'])
            ));
        } else if ($options['input'] === 'array') {
            $builder->appendNormTransformer(new ReversedTransformer(
                new DateTimeToArrayTransformer($options['data_timezone'], $options['data_timezone'], array('year', 'month', 'day'))
            ));
        } else if ($options['input'] !== 'datetime') {
            throw new FormException('The "input" option must be "datetime", "string", "timestamp" or "array".');
        }

        $builder
            ->setAttribute('formatter', $formatter)
            ->setAttribute('widget', $options['widget']);
    }

    public function buildViewBottomUp(FormView $view, FormInterface $form)
    {
        $view->set('widget', $form->getAttribute('widget'));

        if ($view->hasChildren()) {

            $pattern = $form->getAttribute('formatter')->getPattern();

            // set right order with respect to locale (e.g.: de_DE=dd.MM.yy; en_US=M/d/yy)
            // lookup various formats at http://userguide.icu-project.org/formatparse/datetime
            if (preg_match('/^([yMd]+).+([yMd]+).+([yMd]+)$/', $pattern)) {
                $pattern = preg_replace(array('/y+/', '/M+/', '/d+/'), array('{{ year }}', '{{ month }}', '{{ day }}'), $pattern);
            } else {
                // default fallback
                $pattern = '{{ year }}-{{ month }}-{{ day }}';
            }

            $view->set('date_pattern', $pattern);
        }
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'years' => range(date('Y') - 5, date('Y') + 5),
            'months' => range(1, 12),
            'days' => range(1, 31),
            'widget' => 'choice',
            'input' => 'datetime',
            'pattern' => null,
            'format' => \IntlDateFormatter::MEDIUM,
            'data_timezone' => null,
            'user_timezone' => null,
            'csrf_protection' => false,
            // Don't modify \DateTime classes by reference, we treat
            // them like immutable value objects
            'by_reference' => false,
        );
    }

    public function getParent(array $options)
    {
        return $options['widget'] === 'text' ? 'field' : 'form';
    }

    public function getName()
    {
        return 'date';
    }
}
