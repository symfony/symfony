<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier Fabio B. Silva <fabio.bat.silva@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

use Doctrine\Common\Annotations\Reader,
    Symfony\Component\DependencyInjection\Annotation\Autoware,
    Symfony\Component\DependencyInjection\AnnotationCollection;

/**
 * AnnotationClassLoader load class autoware annotations.
 *
 * @author Fabio B. Silva <fabio.bat.silva@gmail.com>
 */
class AnnotationLoader
{
    
    /**
     * @var Doctrine\Common\Annotations\Reader
     */
    protected $reader;
    
    /**
     * @var type 
     */
    protected $class;
    
    
    /**
     *
     * @var \Symfony\Component\DependencyInjection\AnnotationCollection
     */
    protected $collection;
    
    /**
     * @var array
     */
    protected $annotations = array(
        'Symfony\\Component\\DependencyInjection\\Annotation\\Inject'
    );

    /**
     * Constructor.
     *
     * @param Reader $reader
     */
    public function __construct(Reader $reader, $class)
    {
        if(!class_exists($class))
        {
            throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', $class));
        }

        $this->class    = $class;
        $this->reader   = $reader;
    }

    /**
     * add annotation class.
     *
     * @param string $class A fully-qualified class name
     */
    public function addAnnotationClass($class)
    {
        if(!($class instanceof Annotation))
        {
            throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', $class));
        }
        $this->annotations[] = $class;
    }
    
    /**
     * @return Symfony\Component\DependencyInjection\AnnotationCollection 
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * @return array 
     */
    public function getAnnotations()
    {
        return $this->annotations;
    }


    /**
     * Loads from annotations from a class.
     *
     * @param string $class A class name
     *
     * @return AnnotationCollection A AnnotationCollection instance
     *
     * @throws \InvalidArgumentException When can't be parsed
     */
    public function load()
    {
        
        $class              = new \ReflectionClass($this->class);
        $this->collection   = new AnnotationCollection($this->class);
        $properties         = $class->getProperties();
        
        foreach ($this->annotations as $annotationName)
        {
            foreach ($properties as $property)
            {
                $annotation = $this->reader->getPropertyAnnotation($property, $annotationName); 
                
                if($annotation instanceof Annotation)
                {
                    $annotation->setProperty($property->getName());    
                    $this->collection->add($annotation);
                }
            }
        }

        return $this->collection;
    }

}
