<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Form\Type;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Bridge\Doctrine\Form\ChoiceList\DoctrineChoiceLoader;
use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityLoaderInterface;
use Symfony\Bridge\Doctrine\Form\DataTransformer\CollectionToArrayTransformer;
use Symfony\Bridge\Doctrine\Form\EventListener\MergeDoctrineCollectionListener;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\Factory\CachingFactoryDecorator;
use Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface;
use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;
use Symfony\Component\Form\ChoiceList\Factory\PropertyAccessDecorator;
use Symfony\Component\Form\Exception\RuntimeException;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

abstract class DoctrineType extends AbstractType
{
    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @var ChoiceListFactoryInterface
     */
    private $choiceListFactory;

    /**
     * @var DoctrineChoiceLoader[]
     */
    private $choiceLoaders = array();

    public function __construct(ManagerRegistry $registry, PropertyAccessorInterface $propertyAccessor = null, ChoiceListFactoryInterface $choiceListFactory = null)
    {
        $this->registry = $registry;
        $this->choiceListFactory = $choiceListFactory ?: new PropertyAccessDecorator(new DefaultChoiceListFactory(), $propertyAccessor);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['multiple']) {
            $builder
                ->addEventSubscriber(new MergeDoctrineCollectionListener())
                ->addViewTransformer(new CollectionToArrayTransformer(), true)
            ;
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $registry = $this->registry;
        $choiceListFactory = $this->choiceListFactory;
        $choiceLoaders = &$this->choiceLoaders;
        $type = $this;

        $choiceLoader = function (Options $options) use ($choiceListFactory, &$choiceLoaders, $type) {
            // Unless the choices are given explicitly, load them on demand
            if (null === $options['choices']) {
                // Don't cache if the query builder is constructed dynamically
                if ($options['query_builder'] instanceof \Closure) {
                    $hash = null;
                } else {
                    $hash = CachingFactoryDecorator::generateHash(array(
                        $options['em'],
                        $options['class'],
                        $options['query_builder'],
                        $options['loader'],
                    ));

                    if (isset($choiceLoaders[$hash])) {
                        return $choiceLoaders[$hash];
                    }
                }

                if ($options['loader']) {
                    $entityLoader = $options['loader'];
                } elseif (null !== $options['query_builder']) {
                    $entityLoader = $type->getLoader($options['em'], $options['query_builder'], $options['class']);
                } else {
                    $queryBuilder = $options['em']->getRepository($options['class'])->createQueryBuilder('e');
                    $entityLoader = $type->getLoader($options['em'], $queryBuilder, $options['class']);
                }

               $choiceLoader = new DoctrineChoiceLoader(
                    $choiceListFactory,
                    $options['em'],
                    $options['class'],
                    $entityLoader
                );

                if (null !== $hash) {
                    $choiceLoaders[$hash] = $choiceLoader;
                }

                return $choiceLoader;
            }
        };

        $choiceLabel = function (Options $options) {
            // BC with the "property" option
            if ($options['property']) {
                return $options['property'];
            }

            // BC: use __toString() by default
            return function ($entity) {
                return (string) $entity;
            };
        };

        $choiceName = function (Options $options) {
            /** @var ObjectManager $om */
            $om = $options['em'];
            $classMetadata = $om->getClassMetadata($options['class']);
            $ids = $classMetadata->getIdentifierFieldNames();
            $idType = $classMetadata->getTypeOfField(current($ids));

            // If the entity has a single-column, numeric ID, use that ID as
            // field name
            if (1 === count($ids) && in_array($idType, array('integer', 'smallint', 'bigint'))) {
                return function ($entity, $id) {
                    return $id;
                };
            }

            // Otherwise, an incrementing integer is used as name automatically
        };

        // The choices are always indexed by ID (see "choices" normalizer
        // and DoctrineChoiceLoader), unless the ID is composite. Then they
        // are indexed by an incrementing integer.
        // Use the ID/incrementing integer as choice value.
        $choiceValue = function ($entity, $key) {
            return $key;
        };

        $emNormalizer = function (Options $options, $em) use ($registry) {
            /* @var ManagerRegistry $registry */
            if (null !== $em) {
                if ($em instanceof ObjectManager) {
                    return $em;
                }

                return $registry->getManager($em);
            }

            $em = $registry->getManagerForClass($options['class']);

            if (null === $em) {
                throw new RuntimeException(sprintf(
                    'Class "%s" seems not to be a managed Doctrine entity. '.
                    'Did you forget to map it?',
                    $options['class']
                ));
            }

            return $em;
        };

        $choicesNormalizer = function (Options $options, $entities) {
            if (null === $entities || 0 === count($entities)) {
                return $entities;
            }

            // Make sure that the entities are indexed by their ID
            /** @var ObjectManager $om */
            $om = $options['em'];
            $classMetadata = $om->getClassMetadata($options['class']);
            $ids = $classMetadata->getIdentifierFieldNames();

            // We cannot use composite IDs as indices. In that case, keep the
            // given indices
            if (count($ids) > 1) {
                return $entities;
            }

            $entitiesById = array();

            foreach ($entities as $entity) {
                $id = DoctrineChoiceLoader::getIdValue($om, $classMetadata, $entity);
                $entitiesById[$id] = $entity;
            }

            return $entitiesById;
        };

        $resolver->setDefaults(array(
            'em' => null,
            'property' => null, // deprecated, use "choice_label"
            'query_builder' => null,
            'loader' => null, // deprecated, use "choice_loader"
            'choices' => null,
            'choices_as_values' => true,
            'choice_loader' => $choiceLoader,
            'choice_label' => $choiceLabel,
            'choice_name' => $choiceName,
            'choice_value' => $choiceValue,
        ));

        $resolver->setRequired(array('class'));

        $resolver->setNormalizer('em', $emNormalizer);
        $resolver->setNormalizer('choices', $choicesNormalizer);

        $resolver->setAllowedTypes('em', array('null', 'string', 'Doctrine\Common\Persistence\ObjectManager'));
        $resolver->setAllowedTypes('loader', array('null', 'Symfony\Bridge\Doctrine\Form\ChoiceList\EntityLoaderInterface'));
    }

    /**
     * Return the default loader object.
     *
     * @param ObjectManager $manager
     * @param mixed         $queryBuilder
     * @param string        $class
     *
     * @return EntityLoaderInterface
     */
    abstract public function getLoader(ObjectManager $manager, $queryBuilder, $class);

    public function getParent()
    {
        return 'choice';
    }
}
