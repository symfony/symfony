<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Form\DataTransformer;

use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityLoaderInterface;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Doctrine\Common\Collections\Collection;

use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Form\Exception\FormException;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\NoResultException;

class EntityToArrayTransformer implements DataTransformerInterface
{
    /**
     * @var ObjectManager
     */
    private $em;

    /**
     * @var Doctrine\ORM\Mapping\ClassMetadata
     */
    private $class;
    
    /**
     * @var \Doctrine\Common\Persistence\Mapping\ClassMetadata
     */
    private $classMetadata;    
    
    /**
     * Contains the query builder that builds the query for fetching the
     * entities
     *
     * This property should only be accessed through queryBuilder.
     *
     * @var EntityLoaderInterface
     */
    private $entityLoader;
    
    /**
     * The fields of which the identifier of the underlying class consists
     *
     * This property should only be accessed through identifier.
     *
     * @var array
     */
    private $identifier = array();    
    
    private $propertyPath;
    
    private $property;
    
    /**
     * Constructor.
     *
     * @param ObjectManager         $manager      An EntityManager instance
     * @param string                $class        The class name
     * @param string                $property     The property name
     * @param EntityLoaderInterface $entityLoader An optional query builder
     */
    public function __construct(ObjectManager $manager, $class, $property = null, EntityLoaderInterface $entityLoader = null)
    {
        // If a query builder was passed, it must be a closure or QueryBuilder
        // instance
        if (!(null === $queryBuilder || $queryBuilder instanceof QueryBuilder || $queryBuilder instanceof \Closure)) {
            throw new UnexpectedTypeException($queryBuilder, 'Doctrine\ORM\QueryBuilder or \Closure');
        }

        $this->em = $manager;
        $this->class = $class;
        $this->property = $property;
        $this->classMetadata = $this->em->getClassMetadata($class);
        $this->entityLoader = $entityLoader;
        $this->identifier = $this->classMetadata->getIdentifierFieldNames();

        // The property option defines, which property (path) is used for
        // displaying entities as strings
        if ($property) {
            $this->propertyPath = new PropertyPath($property);
        } elseif (!method_exists($this->classMetadata->getName(), '__toString')) {
            // Otherwise expect a __toString() method in the entity
            throw new FormException('Entities passed to the choice field must have a "__toString()" method defined (or you can also override the "property" option).');
        }
    }

    /**
     * Transforms entities into choice keys.
     *
     * @param object  a single entity or NULL
     *
     * @return mixed An array of choice keys, a single key or NULL
     */
    public function transform($entity)
    {
        if (null === $entity || '' === $entity) {
            return array();
        }

        if (!is_object($entity)) {
            throw new UnexpectedTypeException($entity, 'object');
        }

        if ($entity instanceof Collection) {
            throw new \InvalidArgumentException('Expected an object, but got a collection. Did you forget to pass "multiple=true" to an entity field?');
        }
        
        $values = array('id' => current($this->getIdentifierValues($entity)));
        
        if ($this->property) {
            $values[ $this->property ] = $this->propertyPath->getValue($entity);
        }
        
        return $values;
    }

    /**
     * Transforms choice keys into entities.
     *
     * @param  mixed $key   An array of keys, a single key or NULL
     *
     * @return object  a single entity or NULL
     */
    public function reverseTransform($value)
    {
        if (null === $value) {
            return null;
        }

        if (!is_array($value)) {
            throw new UnexpectedTypeException($value, 'array');
        }
        
        if (implode('', $value) === '') {
            return null;
        }        
        
        if (isset($value['id']) && !ctype_digit($value['id']) && !is_int($value['id'])) {
            throw new TransformationFailedException('Identifier is invalid');
        }
        
        $key = $value['id'];

        if(null === $key) {
            return null;
        }

        if (count($this->identifier) > 1 && !is_numeric($key)) {
            throw new UnexpectedTypeException($key, 'numeric');
        }
        
        if ($qb = $this->queryBuilder) {
            $alias = $qb->getRootAlias();
            $where = $qb->expr()->eq($alias.'.'.current($this->identifier), $key);
            try{
                $entity = $qb->andWhere($where)->getQuery()->getSingleResult();
            } catch (NoResultException $e) {
                return null;
            }
        } else {
            $entity = $this->em->find($this->class, $key);
        }

        if (!$entity) {
            return null;
        }

        return $entity;
    }
    
    /**
     * Returns the values of the identifier fields of an entity.
     *
     * Doctrine must know about this entity, that is, the entity must already
     * be persisted or added to the identity map before. Otherwise an
     * exception is thrown.
     *
     * @param  object $entity The entity for which to get the identifier
     *
     * @return array          The identifier values
     *
     * @throws FormException  If the entity does not exist in Doctrine's identity map
     */
    public function getIdentifierValues($entity)
    {
        if (!$this->em->contains($entity)) {
            throw new FormException('Entities passed to the choice field must be managed');
        }

        $this->em->initializeObject($entity);

        return $this->classMetadata->getIdentifierValues($entity);
    }
    
}
