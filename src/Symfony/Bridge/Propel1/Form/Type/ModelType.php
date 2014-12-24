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
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * ModelType class.
 *
 * @author William Durand <william.durand1@gmail.com>
 * @author Toni Uebernickel <tuebernickel@gmail.com>
 *
 * Example using the preferred_choices option.
 *
 * <code>
 *  public function buildForm(FormBuilderInterface $builder, array $options)
 *  {
 *      $builder
 *          ->add('product', 'model', array(
 *              'class' => 'Model\Product',
 *              'query' => ProductQuery::create()
 *                  ->filterIsActive(true)
 *                  ->useI18nQuery($options['locale'])
 *                      ->orderByName()
 *                  ->endUse()
 *              ,
 *              'preferred_choices' => ProductQuery::create()
 *                  ->filterByIsTopProduct(true)
 *              ,
 *          ))
 *      ;
 *   }
 * </code>
 */
class ModelType extends AbstractType
{
    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    /**
     * Constructor.
     *
     * @param PropertyAccessorInterface|null $propertyAccessor
     */
    public function __construct(PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['multiple']) {
            $builder->addViewTransformer(new CollectionToArrayTransformer(), true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $propertyAccessor = $this->propertyAccessor;

        $choiceList = function (Options $options) use ($propertyAccessor) {
            return new ModelChoiceList(
                $options['class'],
                $options['property'],
                $options['choices'],
                $options['query'],
                $options['group_by'],
                $options['preferred_choices'],
                $propertyAccessor,
                $options['index_property']
            );
        };

        $resolver->setDefaults(array(
            'template' => 'choice',
            'multiple' => false,
            'expanded' => false,
            'class' => null,
            'property' => null,
            'query' => null,
            'choices' => null,
            'choice_list' => $choiceList,
            'group_by' => null,
            'by_reference' => false,
            'index_property' => null,
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'choice';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'model';
    }
}
