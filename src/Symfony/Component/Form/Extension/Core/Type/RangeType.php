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
        if (!$options['range'] || !count($options['range'])) {
            throw new FormException('The option "range" is required and must be an array of the start and end values');
        }

        if ($options['range'] && !is_array($options['range'])) {
            throw new UnexpectedTypeException('The option "range" must be an array');
        }
        
        $range = call_user_func_array('range', $options['range']);
        
        $options['choices'] += array_combine($range, $range);
        
        parent::buildForm($builder, $options);
    }

    public function getDefaultOptions(Array $options)
    {
        return array_merge(parent::getDefaultOptions($options), array(
            'range' => array(),
        ));
    }

    public function getName()
    {
        return 'range';
    }

}