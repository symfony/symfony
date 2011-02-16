<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\ChoiceList\EntityChoiceList;
use Symfony\Component\Form\ValueTransformer\TransformationFailedException;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\InvalidOptionsException;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\NoResultException;

/**
 * A field for selecting one or more from a list of Doctrine 2 entities
 *
 * You at least have to pass the entity manager and the entity class in the
 * options "em" and "class".
 *
 * <code>
 * $form->add(new EntityChoiceField('tags', array(
 *     'em' => $em,
 *     'class' => 'Application\Entity\Tag',
 * )));
 * </code>
 *
 * Additionally to the options in ChoiceField, the following options are
 * available:
 *
 *  * em:             The entity manager. Required.
 *  * class:          The class of the selectable entities. Required.
 *  * property:       The property displayed as value of the choices. If this
 *                    option is not available, the field will try to convert
 *                    objects into strings using __toString().
 *  * query_builder:  The query builder for fetching the selectable entities.
 *                    You can also pass a closure that receives the repository
 *                    as single argument and returns a query builder.
 *
 * The following sample outlines the use of the "query_builder" option
 * with closures.
 *
 * <code>
 * $form->add(new EntityChoiceField('tags', array(
 *     'em' => $em,
 *     'class' => 'Application\Entity\Tag',
 *     'query_builder' => function ($repository) {
 *         return $repository->createQueryBuilder('t')->where('t.enabled = 1');
 *     },
 * )));
 * </code>
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class EntityChoiceField extends ChoiceField
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addRequiredOption('em');
        $this->addRequiredOption('class');
        $this->addOption('property');
        $this->addOption('query_builder');

        // Override option - it is not required for this subclass
        $this->addOption('choices', array());

        parent::configure();

        $this->choiceList = new EntityChoiceList(
            $this->getOption('em'),
            $this->getOption('class'),
            $this->getOption('property'),
            $this->getOption('query_builder'),
            $this->getOption('choices'),
            $this->getOption('preferred_choices'),
            $this->getOption('empty_value'),
            $this->isRequired()
        );
    }

    /**
     * Merges the selected and deselected entities into the collection passed
     * when calling setData()
     *
     * @see parent::processData()
     */
    protected function processData($data)
    {
        // reuse the existing collection to optimize for Doctrine
        if ($data instanceof Collection) {
            $currentData = $this->getData();

            if (!$currentData) {
                $currentData = $data;
            } else if (count($data) === 0) {
                $currentData->clear();
            } else {
                // merge $data into $currentData
                foreach ($currentData as $entity) {
                    if (!$data->contains($entity)) {
                        $currentData->removeElement($entity);
                    } else {
                        $data->removeElement($entity);
                    }
                }

                foreach ($data as $entity) {
                    $currentData->add($entity);
                }
            }

            return $currentData;
        }

        return $data;
    }

    /**
     * Transforms choice keys into entities
     *
     * @param  mixed $keyOrKeys   An array of keys, a single key or NULL
     * @return Collection|object  A collection of entities, a single entity
     *                            or NULL
     */
    protected function reverseTransform($keyOrKeys)
    {
        $keyOrKeys = parent::reverseTransform($keyOrKeys);

        if (null === $keyOrKeys) {
            return $this->getOption('multiple') ? new ArrayCollection() : null;
        }

        $notFound = array();

        if (count($this->choiceList->getIdentifier()) > 1) {
            $notFound = array_diff((array)$keyOrKeys, array_keys($this->choiceList->getEntities()));
        } else if ($this->choiceList->getEntities()) {
            $notFound = array_diff((array)$keyOrKeys, array_keys($this->choiceList->getEntities()));
        }

        if (0 === count($notFound)) {
            if (is_array($keyOrKeys)) {
                $result = new ArrayCollection();

                // optimize this into a SELECT WHERE IN query
                foreach ($keyOrKeys as $key) {
                    try {
                        $result->add($this->choiceList->getEntity($key));
                    } catch (NoResultException $e) {
                        $notFound[] = $key;
                    }
                }
            } else {
                try {
                    $result = $this->choiceList->getEntity($keyOrKeys);
                } catch (NoResultException $e) {
                    $notFound[] = $keyOrKeys;
                }
            }
        }

        if (count($notFound) > 0) {
            throw new TransformationFailedException('The entities with keys "%s" could not be found', implode('", "', $notFound));
        }

        return $result;
    }

    /**
     * Transforms entities into choice keys
     *
     * @param  Collection|object  A collection of entities, a single entity or
     *                            NULL
     * @return mixed              An array of choice keys, a single key or
     *                            NULL
     */
    protected function transform($collectionOrEntity)
    {
        if (null === $collectionOrEntity) {
            return $this->getOption('multiple') ? array() : '';
        }

        if (count($this->choiceList->getIdentifier()) > 1) {
            // load all choices
            $availableEntities = $this->choiceList->getEntities();

            if ($collectionOrEntity instanceof Collection) {
                $result = array();

                foreach ($collectionOrEntity as $entity) {
                    // identify choices by their collection key
                    $key = array_search($entity, $availableEntities);
                    $result[] = $key;
                }
            } else {
                $result = array_search($collectionOrEntity, $availableEntities);
            }
        } else {
            if ($collectionOrEntity instanceof Collection) {
                $result = array();

                foreach ($collectionOrEntity as $entity) {
                    $result[] = current($this->choiceList->getIdentifierValues($entity));
                }
            } else {
                $result = current($this->choiceList->getIdentifierValues($collectionOrEntity));
            }
        }

        return parent::transform($result);
    }
}