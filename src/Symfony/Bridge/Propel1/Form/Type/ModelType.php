<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Propel1\Form\Type;

use Symfony\Bridge\Propel1\Form\ChoiceList\ModelChoiceList;
use Symfony\Bridge\Propel1\Form\DataTransformer\CollectionToArrayTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Options;
use Symfony\Component\Form\FormBuilder;

/**
 * ModelType class.
 *
 * @author William Durand <william.durand1@gmail.com>
 */
class ModelType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        if ($options['multiple']) {
            $builder->prependClientTransformer(new CollectionToArrayTransformer());
        }
    }

    public function getDefaultOptions()
    {
        $choiceList = function (Options $options) {
            return new ModelChoiceList(
                $options['class'],
                $options['property'],
                $options['choices'],
                $options['query'],
                $options['group_by']
            );
        };

        return array(
            'template'          => 'choice',
            'multiple'          => false,
            'expanded'          => false,
            'class'             => null,
            'property'          => null,
            'query'             => null,
            'choices'           => null,
            'choice_list'       => $choiceList,
            'group_by'          => null,
            'by_reference'      => false,
        );
    }

    public function getParent(array $options)
    {
        return 'choice';
    }

    public function getName()
    {
        return 'propel_model';
    }
}
