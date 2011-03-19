<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Type;

use Symfony\Component\Form\FieldBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\ChoiceList\EntityChoiceList;
use Symfony\Component\Form\EventListener\MergeCollectionListener;
use Symfony\Component\Form\DataTransformer\EntitiesToArrayTransformer;
use Symfony\Component\Form\DataTransformer\ArrayToChoicesTransformer;
use Symfony\Component\Form\DataTransformer\EntityToIdTransformer;
use Symfony\Component\Form\DataTransformer\ScalarToChoicesTransformer;
use Symfony\Component\Form\DataTransformer\DataTransformerChain;
use Doctrine\ORM\EntityManager;

class EntityType extends AbstractType
{
    private $em;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    public function configure(FieldBuilder $builder, array $options)
    {
        $transformers = array();

        if ($options['multiple']) {
            $builder->addEventSubscriber(new MergeCollectionListener());

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
            $builder->setClientTransformer(new DataTransformerChain($transformers));
        } else {
            $builder->setClientTransformer(current($transformers));
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

    public function getName()
    {
        return 'entity';
    }
}