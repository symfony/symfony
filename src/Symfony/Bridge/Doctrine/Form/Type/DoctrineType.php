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
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityChoiceList;
use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityLoaderInterface;
use Symfony\Bridge\Doctrine\Form\EventListener\MergeDoctrineCollectionListener;
use Symfony\Bridge\Doctrine\Form\DataTransformer\CollectionToArrayTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

abstract class DoctrineType extends AbstractType
{
    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @var array
     */
    private $choiceListCache = array();

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

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $choiceListCache =& $this->choiceListCache;
        $registry = $this->registry;
        $type = $this;

        $loader = function (Options $options) use ($type) {
            if (null !== $options['query_builder']) {
                return $type->getLoader($options['em'], $options['query_builder'], $options['class']);
            }

            return null;
        };

        $choiceList = function (Options $options) use (&$choiceListCache, &$time) {
            // Support for closures
            $propertyHash = is_object($options['property'])
                ? spl_object_hash($options['property'])
                : $options['property'];

            $choiceHashes = $options['choices'];

            // Support for recursive arrays
            if (is_array($choiceHashes)) {
                // A second parameter ($key) is passed, so we cannot use
                // spl_object_hash() directly (which strictly requires
                // one parameter)
                array_walk_recursive($choiceHashes, function ($value) {
                    return spl_object_hash($value);
                });
            }

            $preferredChoiceHashes = $options['preferred_choices'];

            if (is_array($preferredChoiceHashes)) {
                array_walk_recursive($preferredChoiceHashes, function ($value) {
                    return spl_object_hash($value);
                });
            }

            // Support for custom loaders (with query builders)
            $loaderHash = is_object($options['loader'])
                ? spl_object_hash($options['loader'])
                : $options['loader'];

            // Support for closures
            $groupByHash = is_object($options['group_by'])
                ? spl_object_hash($options['group_by'])
                : $options['group_by'];

            $hash = md5(json_encode(array(
                spl_object_hash($options['em']),
                $options['class'],
                $propertyHash,
                $loaderHash,
                $choiceHashes,
                $preferredChoiceHashes,
                $groupByHash
            )));

            if (!isset($choiceListCache[$hash])) {
                $choiceListCache[$hash] = new EntityChoiceList(
                    $options['em'],
                    $options['class'],
                    $options['property'],
                    $options['loader'],
                    $options['choices'],
                    $options['preferred_choices'],
                    $options['group_by']
                );
            }

            return $choiceListCache[$hash];
        };

        $emNormalizer = function (Options $options, $em) use ($registry) {
            /* @var ManagerRegistry $registry */
            if (null !== $em) {
                return $registry->getManager($em);
            }

            return $registry->getManagerForClass($options['class']);
        };

        $resolver->setDefaults(array(
            'em'                => null,
            'property'          => null,
            'query_builder'     => null,
            'loader'            => $loader,
            'choices'           => null,
            'choice_list'       => $choiceList,
            'group_by'          => null,
        ));

        $resolver->setRequired(array('class'));

        $resolver->setNormalizers(array(
            'em' => $emNormalizer,
        ));
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
