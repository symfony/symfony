<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Form\EventListener;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\PersistentCollection;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Util\PropertyPath;

/**
 * EntityCollectionListener.
 *
 * @author Marc Weistroff <marc.weistroff@sensio.com>
 */
class EntityCollectionListener implements EventSubscriberInterface
{
    /**
     * Entity FQCN.
     *
     * @var string
     */
    private $class;

    /**
     * Registry.
     *
     * @var Registry
     */
    private $registry;

    /**
     * Entity.
     *
     * @var object
     */
    private $relation;

    /**
     * getSubscribedEvents.
     *
     * @return array
     */
    static public function getSubscribedEvents()
    {
        return array(FormEvents::BIND_NORM_DATA => 'onBindNormData');
    }

    /**
     * __construct
     *
     * @param RegistryInterface $registry
     * @param string $entity FQCN
     */
    public function __construct(RegistryInterface $registry, $class)
    {
        $this->registry = $registry;
        $this->class = $class;
    }

    /**
     * onBindNormData
     *
     * @param FilterDataEvent $event
     */
    public function onBindNormData(FilterDataEvent $event)
    {
        // We get the related entity
        $relation = $event->getForm()->getParent()->getData();
        if (is_object($relation)) {
            $em = $this->registry->getEntityManagerForObject($relation);
            if ($em) {
                $this->relation = $relation;
            }
        }

        $collection = $event->getForm()->getData();
        $data = $event->getData();

        // $collection can be an instance of PersistentCollection if entity was fetched from DB
        // or ArrayCollection otherwise.
        if ($collection instanceof PersistentCollection) {
            $this->processPersistentCollection($collection, $data);
        } elseif ($collection instanceof ArrayCollection) {
            $this->processArrayCollection($collection, $data);
        }
    }

    /**
     * Instanciate an entity and fill it with $data using
     * form PropertyPath.
     *
     * This method can be overriden in order to fit your needs.
     *
     * @param array $data
     * @return object
     */
    protected function createEntity($data)
    {
        $object = new $this->class();
        foreach ($data as $key => $value) {
            $path = new PropertyPath($key);
            $path->setValue($object, $value);
        }

        // We set the relation.
        if ($this->relation) {
            $em = $this->registry->getEntityManagerForObject($object);
            $metadata = $em->getClassMetadata(get_class($object));

            foreach ($metadata->getAssociationMappings() as $association) {
                if ($association['targetEntity'] === get_class($this->relation)) {
                    $path = new PropertyPath($association['fieldName']);
                    $path->setValue($object, $this->relation);
                }
            }
        }

        return $object;
    }

    /**
     * Removes object of entity manager.
     *
     * This method can be overriden in order to fit your needs.
     *
     * @param mixed $object
     */
    protected function removeEntity($object)
    {
        $em = $this->registry->getEntityManagerForObject($object);
        $em->remove($object);
    }

    /**
     * processPersistentCollection.
     *
     * @param Collection $collection
     * @param mixed $data
     */
    protected function processPersistentCollection(PersistentCollection $collection)
    {
        foreach ($collection->getInsertDiff() as $k => $data) {
            $collection->removeElement($data);
            $collection->add($this->createEntity($data));
        }

        foreach ($collection->getDeleteDiff() as $deleted) {
            $this->removeEntity($deleted);
        }
    }

    /**
     * processArrayCollection.
     *
     * @param Collection $collection
     * @param mixed $data
     */
    protected function processArrayCollection(ArrayCollection $collection)
    {
        foreach ($collection as $k => $data) {
            $collection[$k] = $this->createEntity($data);
        }
    }
}

