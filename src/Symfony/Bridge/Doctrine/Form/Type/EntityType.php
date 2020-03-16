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

use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bridge\Doctrine\Form\ChoiceList\ORMQueryBuilderLoader;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EntityType extends DoctrineType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        // Invoke the query builder closure so that we can cache choice lists
        // for equal query builders
        $queryBuilderNormalizer = function (Options $options, $queryBuilder) {
            if (\is_callable($queryBuilder)) {
                $queryBuilder = $queryBuilder($options['em']->getRepository($options['class']));

                if (null !== $queryBuilder && !$queryBuilder instanceof QueryBuilder) {
                    throw new UnexpectedTypeException($queryBuilder, 'Doctrine\ORM\QueryBuilder');
                }
            }

            return $queryBuilder;
        };

        $resolver->setNormalizer('query_builder', $queryBuilderNormalizer);
        $resolver->setAllowedTypes('query_builder', ['null', 'callable', 'Doctrine\ORM\QueryBuilder']);
    }

    /**
     * Return the default loader object.
     *
     * @param QueryBuilder $queryBuilder
     *
     * @return ORMQueryBuilderLoader
     */
    public function getLoader(ObjectManager $manager, $queryBuilder, string $class)
    {
        if (!$queryBuilder instanceof QueryBuilder) {
            throw new \TypeError(sprintf('Expected an instance of "%s", but got "%s".', QueryBuilder::class, \is_object($queryBuilder) ? \get_class($queryBuilder) : \gettype($queryBuilder)));
        }

        return new ORMQueryBuilderLoader($queryBuilder);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'entity';
    }

    /**
     * We consider two query builders with an equal SQL string and
     * equal parameters to be equal.
     *
     * @param QueryBuilder $queryBuilder
     *
     * @internal This method is public to be usable as callback. It should not
     *           be used in user code.
     */
    public function getQueryBuilderPartsForCachingHash($queryBuilder): ?array
    {
        if (!$queryBuilder instanceof QueryBuilder) {
            throw new \TypeError(sprintf('Expected an instance of "%s", but got "%s".', QueryBuilder::class, \is_object($queryBuilder) ? \get_class($queryBuilder) : \gettype($queryBuilder)));
        }

        return [
            $queryBuilder->getQuery()->getSQL(),
            array_map([$this, 'parameterToArray'], $queryBuilder->getParameters()->toArray()),
        ];
    }

    /**
     * Converts a query parameter to an array.
     */
    private function parameterToArray(Parameter $parameter): array
    {
        return [$parameter->getName(), $parameter->getType(), $parameter->getValue()];
    }
}

interface_exists(ObjectManager::class);
