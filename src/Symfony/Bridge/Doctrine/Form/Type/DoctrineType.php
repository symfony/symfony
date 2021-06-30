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

use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bridge\Doctrine\Form\ChoiceList\DoctrineChoiceLoader;
use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityLoaderInterface;
use Symfony\Bridge\Doctrine\Form\ChoiceList\IdReader;
use Symfony\Bridge\Doctrine\Form\DataTransformer\CollectionToArrayTransformer;
use Symfony\Bridge\Doctrine\Form\EventListener\MergeDoctrineCollectionListener;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\ChoiceList\Factory\CachingFactoryDecorator;
use Symfony\Component\Form\Exception\RuntimeException;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Service\ResetInterface;

abstract class DoctrineType extends AbstractType implements ResetInterface
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
     * @var EntityLoaderInterface[]
     */
    private $entityLoaders = [];

    /**
     * Creates the label for a choice.
     *
     * For backwards compatibility, objects are cast to strings by default.
     *
     * @internal This method is public to be usable as callback. It should not
     *           be used in user code.
     */
    public static function createChoiceLabel(object $choice): string
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
     * @param int|string $key   The choice key
     * @param string     $value The choice value. Corresponds to the object's
     *                          ID here.
     *
     * @internal This method is public to be usable as callback. It should not
     *           be used in user code.
     */
    public static function createChoiceName(object $choice, $key, string $value): string
    {
        return str_replace('-', '_', (string) $value);
    }

    /**
     * Gets important parts from QueryBuilder that will allow to cache its results.
     * For instance in ORM two query builders with an equal SQL string and
     * equal parameters are considered to be equal.
     *
     * @param object $queryBuilder A query builder, type declaration is not present here as there
     *                             is no common base class for the different implementations
     *
     * @return array|null Array with important QueryBuilder parts or null if
     *                    they can't be determined
     *
     * @internal This method is public to be usable as callback. It should not
     *           be used in user code.
     */
    public function getQueryBuilderPartsForCachingHash(object $queryBuilder): ?array
    {
        return null;
    }

    public function __construct(ManagerRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['multiple'] && interface_exists(Collection::class)) {
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
                // If there is no QueryBuilder we can safely cache
                $vary = [$options['em'], $options['class']];

                // also if concrete Type can return important QueryBuilder parts to generate
                // hash key we go for it as well, otherwise fallback on the instance
                if ($options['query_builder']) {
                    $vary[] = $this->getQueryBuilderPartsForCachingHash($options['query_builder']) ?? $options['query_builder'];
                }

                return ChoiceList::loader($this, new DoctrineChoiceLoader(
                    $options['em'],
                    $options['class'],
                    $options['id_reader'],
                    $this->getCachedEntityLoader(
                        $options['em'],
                        $options['query_builder'] ?? $options['em']->getRepository($options['class'])->createQueryBuilder('e'),
                        $options['class'],
                        $vary
                    )
                ), $vary);
            }

            return null;
        };

        $choiceName = function (Options $options) {
            // If the object has a single-column, numeric ID, use that ID as
            // field name. We can only use numeric IDs as names, as we cannot
            // guarantee that a non-numeric ID contains a valid form name
            if ($options['id_reader'] instanceof IdReader && $options['id_reader']->isIntId()) {
                return ChoiceList::fieldName($this, [__CLASS__, 'createChoiceName']);
            }

            // Otherwise, an incrementing integer is used as name automatically
            return null;
        };

        // The choices are always indexed by ID (see "choices" normalizer
        // and DoctrineChoiceLoader), unless the ID is composite. Then they
        // are indexed by an incrementing integer.
        // Use the ID/incrementing integer as choice value.
        $choiceValue = function (Options $options) {
            // If the entity has a single-column ID, use that ID as value
            if ($options['id_reader'] instanceof IdReader && $options['id_reader']->isSingleId()) {
                return ChoiceList::value($this, [$options['id_reader'], 'getIdValue'], $options['id_reader']);
            }

            // Otherwise, an incrementing integer is used as value automatically
            return null;
        };

        $emNormalizer = function (Options $options, $em) {
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
                $queryBuilder = $queryBuilder($options['em']->getRepository($options['class']));
            }

            return $queryBuilder;
        };

        // Set the "id_reader" option via the normalizer. This option is not
        // supposed to be set by the user.
        $idReaderNormalizer = function (Options $options) {
            // The ID reader is a utility that is needed to read the object IDs
            // when generating the field values. The callback generating the
            // field values has no access to the object manager or the class
            // of the field, so we store that information in the reader.
            // The reader is cached so that two choice lists for the same class
            // (and hence with the same reader) can successfully be cached.
            return $this->getCachedIdReader($options['em'], $options['class']);
        };

        $resolver->setDefaults([
            'em' => null,
            'query_builder' => null,
            'choices' => null,
            'choice_loader' => $choiceLoader,
            'choice_label' => ChoiceList::label($this, [__CLASS__, 'createChoiceLabel']),
            'choice_name' => $choiceName,
            'choice_value' => $choiceValue,
            'id_reader' => null, // internal
            'choice_translation_domain' => false,
        ]);

        $resolver->setRequired(['class']);

        $resolver->setNormalizer('em', $emNormalizer);
        $resolver->setNormalizer('query_builder', $queryBuilderNormalizer);
        $resolver->setNormalizer('id_reader', $idReaderNormalizer);

        $resolver->setAllowedTypes('em', ['null', 'string', ObjectManager::class]);
    }

    /**
     * Return the default loader object.
     *
     * @return EntityLoaderInterface
     */
    abstract public function getLoader(ObjectManager $manager, object $queryBuilder, string $class);

    /**
     * @return string
     */
    public function getParent()
    {
        return ChoiceType::class;
    }

    public function reset()
    {
        $this->idReaders = [];
        $this->entityLoaders = [];
    }

    private function getCachedIdReader(ObjectManager $manager, string $class): ?IdReader
    {
        $hash = CachingFactoryDecorator::generateHash([$manager, $class]);

        if (isset($this->idReaders[$hash])) {
            return $this->idReaders[$hash];
        }

        $idReader = new IdReader($manager, $manager->getClassMetadata($class));

        // don't cache the instance for composite ids that cannot be optimized
        return $this->idReaders[$hash] = $idReader->isSingleId() ? $idReader : null;
    }

    private function getCachedEntityLoader(ObjectManager $manager, object $queryBuilder, string $class, array $vary): EntityLoaderInterface
    {
        $hash = CachingFactoryDecorator::generateHash($vary);

        return $this->entityLoaders[$hash] ?? ($this->entityLoaders[$hash] = $this->getLoader($manager, $queryBuilder, $class));
    }
}
