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

use Symfony\Component\Form\AbstractType;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Bridge\Doctrine\Form\EventListener\EntityCollectionListener;
use Symfony\Component\Form\FormBuilder;


/**
 * EntityCollectionType.
 *
 * This type can be used to ease the process of adding / removing entities
 * from a Collection.
 *
 * It might not work well if on the same entity, you have two different
 * relations to the same tables.
 * Be also careful of the default behavior of the EntityCollectionListener.
 *
 * @author Marc Weistroff <marc.weistroff@sensio.com>
 */
class EntityCollectionType extends AbstractType
{
    protected $registry;

    public function __construct(RegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilder $builder, array $options)
    {
        $collectionListener = new EntityCollectionListener($this->registry, $options['class']);

        $builder->addEventSubscriber($collectionListener);
    }

    public function getDefaultOptions(array $options)
    {
        return array('class' => null);
    }

    public function getParent(array $options)
    {
        return 'collection';
    }

    public function getName()
    {
        return 'entity_collection';
    }
}

