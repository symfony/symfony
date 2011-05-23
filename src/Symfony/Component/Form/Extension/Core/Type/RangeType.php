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

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\FormBuilder;

class RangeType extends ChoiceType
{
    public function buildForm(FormBuilder $builder, Array $options)
    {
        if (!$options['start']) {
            throw new FormException('The option "start" is required');
        }
        
        if (!$options['end']) {
            throw new FormException('The option "end" is required');
        }
        
        $range = call_user_func_array('range', array(
            $options['start'],
            $options['end'],
            $options['step']
        ));
        
        $options['choices'] += array_combine($range, $range);
        
        parent::buildForm($builder, $options);
    }
    
    public function getDefaultOptions(Array $options)
    {
        return array_merge(parent::getDefaultOptions($options), array(
            'start' => null,
            'end'   => null,
            'step'  => 1,
        ));
    }

    public function getName()
    {
        return 'range';
    }
}