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
use Symfony\Bridge\Doctrine\Form\ChoiceList\IdReader;
use Symfony\Bridge\Doctrine\Form\DataTransformer\CollectionToArrayTransformer;
use Symfony\Bridge\Doctrine\Form\EventListener\MergeDoctrineCollectionListener;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\Factory\CachingFactoryDecorator;
use Symfony\Component\Form\Exception\RuntimeException;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class DoctrineType extends AbstractType
{
    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @var IdReader[]
     */
    private $idReaders = [];

    /**
     * @var DoctrineChoiceLoader[]
     */
    private $choiceLoaders = [];

    /**
     * Creates the label for a choice.
     *
     * For backwards compatibility, objects are cast to strings by default.
     *
     * @param object $choice The object
     *
     * @return string The string representation of the object
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
     * @param object     $choice The object
     * @param int|string $key    The choice key
     * @param string     $value  The choice value. Corresponds to the object's
     *                           ID here.
     *
     * @return string The field name
     *
     * @internal This method is public to be usable as callback. It should not
     *           be used in user code.
     */
    public static function createChoiceName($choice, $key, $value)
    {
        return str_replace('-', '_', (string) $value);
    }

    /**
     * Gets important parts from QueryBuilder that will allow to cache its results.
     * For instance in ORM two query builders with an equal SQL string and
     * equal parameters are considered to be equal.
     *
     * @param object $queryBuilder
     *
     * @return array|false Array with important QueryBuilder parts or false if
     *                     they can't be determined
     *
     * @internal This method is public to be usable as callback. It should not
     *           be used in user code.
     */
    public function getQueryBuilderPartsForCachingHash($queryBuilder)
    {
        return false;
    }

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
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
        $choiceLoader = function (Options $options) {
            // Unless the choices are given explicitly, load them on demand
            if (null === $options['choices']) {
                $hash = null;
                $qbParts = null;

                // If there is no QueryBuilder we can safely cache DoctrineChoiceLoader,
                // also if concrete Type can return important QueryBuilder parts to generate
                // hash key we go for it as well
                if (!$options['query_builder'] || false !== ($qbParts = $this->getQueryBuilderPartsForCachingHash($options['query_builder']))) {
                    $hash = CachingFactoryDecorator::generateHash([
                        $options['em'],
                        $options['class'],
                        $qbParts,
                    ]);

                    if (isset($this->choiceLoaders[$hash])) {
                        return $this->choiceLoaders[$hash];
                    }
                }

                if (null !== $options['query_builder']) {
                    $entityLoader = $this->getLoader($options['em'], $options['query_builder'], $options['class']);
                } else {
                    $queryBuilder = $options['em']->getRepository($options['class'])->createQueryBuilder('e');
                    $entityLoader = $this->getLoader($options['em'], $queryBuilder, $options['class']);
                }

                $doctrineChoiceLoader = new DoctrineChoiceLoader(
                    $options['em'],
                    $options['class'],
                    $options['id_reader'],
                    $entityLoader
                );

                if (null !== $hash) {
                    $this->choiceLoaders[$hash] = $doctrineChoiceLoader;
                }

                return $doctrineChoiceLoader;
            }

            return null;
        };

        $choiceName = function (Options $options) {
            /** @var IdReader $idReader */
            $idReader = $options['id_reader'];

            // If the object has a single-column, numeric ID, use that ID as
            // field name. We can only use numeric IDs as names, as we cannot
            // guarantee that a non-numeric ID contains a valid form name
            if ($idReader->isIntId()) {
                return [__CLASS__, 'createChoiceName'];
            }

            // Otherwise, an incrementing integer is used as name automatically
            return null;
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
                return [$idReader, 'getIdValue'];
            }

            // Otherwise, an incrementing integer is used as value automatically
            return null;
        };

        $emNormalizer = function (Options $options, $em) {
            /* @var ManagerRegistry $registry */
            if (null !== $em) {
                if ($em instanceof ObjectManager) {
                    return $em;
                }

                return $this->registry->getManager($em);
            }

            $em = $this->registry->getManagerForClass($options['class']);

            if (null === $em) {
                throw new RuntimeException(sprintf('Class "%s" seems not to be a managed Doctrine entity. Did you forget to map it?', $options['class']));
            }

            return $em;
        };

        // Invoke the query builder closure so that we can cache choice lists
        // for equal query builders
        $queryBuilderNormalizer = function (Options $options, $queryBuilder) {
            if (\is_callable($queryBuilder)) {
                $queryBuilder = \call_user_func($queryBuilder, $options['em']->getRepository($options['class']));
            }

            return $queryBuilder;
        };

        // Set the "id_reader" option via the normalizer. This option is not
        // supposed to be set by the user.
        $idReaderNormalizer = function (Options $options) {
            $hash = CachingFactoryDecorator::generateHash([
                $options['em'],
                $options['class'],
            ]);

            // The ID reader is a utility that is needed to read the object IDs
            // when generating the field values. The callback generating the
            // field values has no access to the object manager or the class
            // of the field, so we store that information in the reader.
            // The reader is cached so that two choice lists for the same class
            // (and hence with the same reader) can successfully be cached.
            if (!isset($this->idReaders[$hash])) {
                $classMetadata = $options['em']->getClassMetadata($options['class']);
                $this->idReaders[$hash] = new IdReader($options['em'], $classMetadata);
            }

            return $this->idReaders[$hash];
        };

        $resolver->setDefaults([
            'em' => null,
            'query_builder' => null,
            'choices' => null,
            'choice_loader' => $choiceLoader,
            'choice_label' => [__CLASS__, 'createChoiceLabel'],
            'choice_name' => $choiceName,
            'choice_value' => $choiceValue,
            'id_reader' => null, // internal
            'choice_translation_domain' => false,
        ]);

        $resolver->setRequired(['class']);

        $resolver->setNormalizer('em', $emNormalizer);
        $resolver->setNormalizer('query_builder', $queryBuilderNormalizer);
        $resolver->setNormalizer('id_reader', $idReaderNormalizer);

        $resolver->setAllowedTypes('em', ['null', 'string', 'Doctrine\Common\Persistence\ObjectManager']);
    }

    /**
     * Return the default loader object.
     *
     * @param mixed  $queryBuilder
     * @param string $class
     *
     * @return EntityLoaderInterface
     */
    abstract public function getLoader(ObjectManager $manager, $queryBuilder, $class);

    public function getParent()
    {
        return 'Symfony\Component\Form\Extension\Core\Type\ChoiceType';
    }

    public function reset()
    {
        $this->choiceLoaders = [];
    }
}
