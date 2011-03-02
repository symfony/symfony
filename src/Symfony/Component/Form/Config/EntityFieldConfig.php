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
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\ChoiceList\EntityChoiceList;
use Symfony\Component\Form\ValueTransformer\MergeCollectionTransformer;
use Symfony\Component\Form\ValueTransformer\EntitiesToArrayTransformer;
use Symfony\Component\Form\ValueTransformer\ArrayToChoicesTransformer;
use Symfony\Component\Form\ValueTransformer\EntityToIdTransformer;
use Symfony\Component\Form\ValueTransformer\ScalarToChoicesTransformer;
use Symfony\Component\Form\ValueTransformer\ValueTransformerChain;
use Doctrine\ORM\EntityManager;

class EntityFieldConfig extends AbstractFieldConfig
{
    private $em;

    public function __construct(FormFactoryInterface $factory, EntityManager $em)
    {
        parent::__construct($factory);

        $this->em = $em;
    }

    public function configure(FieldInterface $field, array $options)
    {
        $transformers = array();

        if ($options['multiple']) {
            $transformers[] = new MergeCollectionTransformer($field);
            $transformers[] = new EntitiesToArrayTransformer($options['choice_list']);

            if ($options['expanded']) {
                $transformers[] = new ArrayToChoicesTransformer($options['choice_list']);
            }
        } else {
            $transformers[] = new EntityToIdTransformer($options['choice_list']);

            if ($options['expanded']) {
                $transformers[] = new ScalarToChoicesTransformer($options['choice_list']);
            }
        }

        if (count($transformers) > 1) {
            $field->setValueTransformer(new ValueTransformerChain($transformers));
        } else {
            $field->setValueTransformer(current($transformers));
        }
    }

    public function getDefaultOptions(array $options)
    {
        $defaultOptions = array(
            'template' => 'choice',
            'multiple' => false,
            'expanded' => false,
            'em' => $this->em,
            'class' => null,
            'property' => null,
            'query_builder' => null,
            'choices' => array(),
            'preferred_choices' => array(),
            'multiple' => false,
            'expanded' => false,
        );

        $options = array_replace($defaultOptions, $options);

        if (!isset($options['choice_list'])) {
            $defaultOptions['choice_list'] = new EntityChoiceList(
                $options['em'],
                $options['class'],
                $options['property'],
                $options['query_builder'],
                $options['choices'],
                $options['preferred_choices']
            );
        }

        return $defaultOptions;
    }

    public function getParent(array $options)
    {
        return 'choice';
    }

    public function getIdentifier()
    {
        return 'entity';
    }
}