<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabio B. Silva <fabio.bat.silva@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

/**
 * A AnnotationConfiguration represents a set of Autoware instances.
 *
 * (c) Fabio B. Silva <fabio.bat.silva@gmail.com>
 *
 * @api
 */
class AnnotationCollection implements \IteratorAggregate
{

    /**
     * @var array
     */
    private $data;
    /**
     * @var string
     */
    private $targetClass;

    /**
     * Constructor.
     *
     * @api
     */
    public function __construct($class)
    {
        if(!class_exists($class))
        {
            throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', $class));
        }
        
        $this->targetClass  = $class;
        $this->data         = array();
    }

    /**
     * @return \ArrayIterator 
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->data);
    }

    /**
     * @return string 
     */
    public function getTargetClass()
    {
        return $this->targetClass;
    }


    /**
     * @param Annotation $annotation 
     */
    public function add(Annotation $annotation)
    {
        $this->data[$annotation->getProperty()] = $annotation;
    }
    
    
    /**
     * @param string $property
     */
    public function has($property)
    {
        return array_key_exists($property, $this->data);
    }

    /**
     * @param string $property
     * @return Annotation 
     */
    public function get($property)
    {
        if ($this->has($property))
        {
            return $this->data[$property];
        }
        return null;
    }

}