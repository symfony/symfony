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
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * ModelType class.
 *
 * @author William Durand <william.durand1@gmail.com>
 */
class ModelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['multiple']) {
            $builder->addViewTransformer(new CollectionToArrayTransformer(), true);
        }
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
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

        $resolver->setDefaults(array(
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
        ));
    }

    public function getParent()
    {
        return 'choice';
    }

    public function getName()
    {
        return 'model';
    }
}
