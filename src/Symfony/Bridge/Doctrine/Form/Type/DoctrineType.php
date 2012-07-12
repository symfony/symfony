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

        $loader = function (Options $options) use ($type, $registry) {
            if (null !== $options['query_builder']) {
                $manager = $registry->getManager($options['em']);

                return $type->getLoader($manager, $options['query_builder'], $options['class']);
            }

            return null;
        };

        $choiceList = function (Options $options) use ($registry, &$choiceListCache, &$time) {
            $manager = $registry->getManager($options['em']);

            $choiceHashes = is_array($options['choices'])
                ? array_map('spl_object_hash', $options['choices'])
                : $options['choices'];

            $hash = md5(json_encode(array(
                spl_object_hash($manager),
                $options['class'],
                $options['property'],
                $options['loader'],
                $choiceHashes,
                $options['group_by']
            )));

            if (!isset($choiceListCache[$hash])) {
                $choiceListCache[$hash] = new EntityChoiceList(
                    $manager,
                    $options['class'],
                    $options['property'],
                    $options['loader'],
                    $options['choices'],
                    $options['group_by']
                );
            }

            return $choiceListCache[$hash];
        };

        $resolver->setDefaults(array(
            'em'                => null,
            'class'             => null,
            'property'          => null,
            'query_builder'     => null,
            'loader'            => $loader,
            'choices'           => null,
            'choice_list'       => $choiceList,
            'group_by'          => null,
        ));
    }

    /**
     * Return the default loader object.
     *
     * @param ObjectManager $manager
     * @param mixed         $queryBuilder
     * @param string        $class
     * @return EntityLoaderInterface
     */
    abstract public function getLoader(ObjectManager $manager, $queryBuilder, $class);

    public function getParent()
    {
        return 'choice';
    }
}
