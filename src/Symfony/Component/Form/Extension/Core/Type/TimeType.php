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
use Symfony\Component\Form\Extension\Core\ChoiceList\PaddedChoiceList;
use Symfony\Component\Form\ReversedTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToTimestampTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToArrayTransformer;
use Symfony\Component\Form\FormView;

class TimeType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $hourOptions = $minuteOptions = $secondOptions = array();
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

    public function buildView(FormView $view, FormInterface $form)
    {
        $view->set('widget', $form->getAttribute('widget'));
        $view->set('with_seconds', $form->getAttribute('with_seconds'));
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
