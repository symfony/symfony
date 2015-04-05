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
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\ChoiceList\DoctrineChoiceLoader;
use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityLoaderInterface;
use Symfony\Bridge\Doctrine\Form\ChoiceList\IdReader;
use Symfony\Bridge\Doctrine\Form\DataTransformer\CollectionToArrayTransformer;
use Symfony\Bridge\Doctrine\Form\EventListener\MergeDoctrineCollectionListener;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\Factory\CachingFactoryDecorator;
use Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface;
use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;
use Symfony\Component\Form\ChoiceList\Factory\PropertyAccessDecorator;
use Symfony\Component\Form\Exception\RuntimeException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
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
     * @var IdReader[]
     */
    private $idReaders = array();

    /**
     * @var DoctrineChoiceLoader[]
     */
    private $choiceLoaders = array();

    /**
     * Creates the label for a choice.
     *
     * For backwards compatibility, objects are cast to strings by default.
     *
     * @param object $choice The object.
     *
     * @return string The string representation of the object.
     *
     * @internal This method is public to be usable as callback. It should not
     *           be used in user code.
     */
    public static function createChoiceLabel($choice)
    {
        return (string) $choice;
    }

    /**
     * Creates the field name for a choice.
     *
     * This method is used to generate field names if the underlying object has
     * a single-column integer ID. In that case, the value of the field is
     * the ID of the object. That ID is also used as field name.
     *
     * @param object     $choice The object.
     * @param int|string $key    The choice key.
     * @param string     $value  The choice value. Corresponds to the object's
     *                           ID here.
     *
     * @return string The field name.
     *
     * @internal This method is public to be usable as callback. It should not
     *           be used in user code.
     */
    public static function createChoiceName($choice, $key, $value)
    {
        return (string) $value;
    }

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
        $idReaders = &$this->idReaders;
        $choiceLoaders = &$this->choiceLoaders;

        $choiceLoader = function (Options $options) use ($choiceListFactory, &$choiceLoaders) {
            // This closure and the "query_builder" options should be pushed to
            // EntityType in Symfony 3.0 as they are specific to the ORM

            // Unless the choices are given explicitly, load them on demand
            if (null === $options['choices']) {
                // We consider two query builders with an equal SQL string and
                // equal parameters to be equal
                $qbParts = $options['query_builder']
                    ? array(
                        $options['query_builder']->getQuery()->getSQL(),
                        $options['query_builder']->getParameters()->toArray(),
                    )
                    : null;

                $hash = CachingFactoryDecorator::generateHash(array(
                    $options['em'],
                    $options['class'],
                    $qbParts,
                    $options['loader'],
                ));

                if (isset($choiceLoaders[$hash])) {
                    return $choiceLoaders[$hash];
                }

                if ($options['loader']) {
                    $entityLoader = $options['loader'];
                } elseif (null !== $options['query_builder']) {
                    $entityLoader = $this->getLoader($options['em'], $options['query_builder'], $options['class']);
                } else {
                    $queryBuilder = $options['em']->getRepository($options['class'])->createQueryBuilder('e');
                    $entityLoader = $this->getLoader($options['em'], $queryBuilder, $options['class']);
                }

                $choiceLoaders[$hash] = new DoctrineChoiceLoader(
                    $choiceListFactory,
                    $options['em'],
                    $options['class'],
                    $options['id_reader'],
                    $entityLoader
                );

                return $choiceLoaders[$hash];
            }
        };

        $choiceLabel = function (Options $options) {
            // BC with the "property" option
            if ($options['property']) {
                return $options['property'];
            }

            // BC: use __toString() by default
            return array(__CLASS__, 'createChoiceLabel');
        };

        $choiceName = function (Options $options) {
            /** @var IdReader $idReader */
            $idReader = $options['id_reader'];

            // If the object has a single-column, numeric ID, use that ID as
            // field name. We can only use numeric IDs as names, as we cannot
            // guarantee that a non-numeric ID contains a valid form name
            if ($idReader->isIntId()) {
                return array(__CLASS__, 'createChoiceName');
            }

            // Otherwise, an incrementing integer is used as name automatically
        };

        // The choices are always indexed by ID (see "choices" normalizer
        // and DoctrineChoiceLoader), unless the ID is composite. Then they
        // are indexed by an incrementing integer.
        // Use the ID/incrementing integer as choice value.
        $choiceValue = function (Options $options) {
            /** @var IdReader $idReader */
            $idReader = $options['id_reader'];

            // If the entity has a single-column ID, use that ID as value
            if ($idReader->isSingleId()) {
                return array($idReader, 'getIdValue');
            }

            // Otherwise, an incrementing integer is used as value automatically
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

        // deprecation note
        $propertyNormalizer = function (Options $options, $propertyName) {
            if ($propertyName) {
                trigger_error('The "property" option is deprecated since version 2.7 and will be removed in 3.0. Use "choice_label" instead.', E_USER_DEPRECATED);
            }

            return $propertyName;
        };

        // Invoke the query builder closure so that we can cache choice lists
        // for equal query builders
        $queryBuilderNormalizer = function (Options $options, $queryBuilder) {
            if (is_callable($queryBuilder)) {
                $queryBuilder = call_user_func($queryBuilder, $options['em']->getRepository($options['class']));

                if (!$queryBuilder instanceof QueryBuilder) {
                    throw new UnexpectedTypeException($queryBuilder, 'Doctrine\ORM\QueryBuilder');
                }
            }

            return $queryBuilder;
        };

        // deprecation note
        $loaderNormalizer = function (Options $options, $loader) {
            if ($loader) {
                trigger_error('The "loader" option is deprecated since version 2.7 and will be removed in 3.0. Override getLoader() instead.', E_USER_DEPRECATED);
            }

            return $loader;
        };

        // Set the "id_reader" option via the normalizer. This option is not
        // supposed to be set by the user.
        $idReaderNormalizer = function (Options $options) use (&$idReaders) {
            $hash = CachingFactoryDecorator::generateHash(array(
                $options['em'],
                $options['class'],
            ));

            // The ID reader is a utility that is needed to read the object IDs
            // when generating the field values. The callback generating the
            // field values has no access to the object manager or the class
            // of the field, so we store that information in the reader.
            // The reader is cached so that two choice lists for the same class
            // (and hence with the same reader) can successfully be cached.
            if (!isset($idReaders[$hash])) {
                $classMetadata = $options['em']->getClassMetadata($options['class']);
                $idReaders[$hash] = new IdReader($options['em'], $classMetadata);
            }

            return $idReaders[$hash];
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
            'id_reader' => null, // internal
            'choice_translation_domain' => false,
        ));

        $resolver->setRequired(array('class'));

        $resolver->setNormalizer('em', $emNormalizer);
        $resolver->setNormalizer('property', $propertyNormalizer);
        $resolver->setNormalizer('query_builder', $queryBuilderNormalizer);
        $resolver->setNormalizer('loader', $loaderNormalizer);
        $resolver->setNormalizer('id_reader', $idReaderNormalizer);

        $resolver->setAllowedTypes('em', array('null', 'string', 'Doctrine\Common\Persistence\ObjectManager'));
        $resolver->setAllowedTypes('loader', array('null', 'Symfony\Bridge\Doctrine\Form\ChoiceList\EntityLoaderInterface'));
        $resolver->setAllowedTypes('query_builder', array('null', 'callable', 'Doctrine\ORM\QueryBuilder'));
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
