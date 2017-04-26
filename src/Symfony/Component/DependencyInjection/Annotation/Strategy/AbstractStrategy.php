<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabio B. Silva <fabio.bat.silva@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Annotation\Strategy;

use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Annotation class for @Inject().
 *
 * @author Fabio B. Silva <fabio.bat.silva@gmail.com>
 */
abstract class AbstractStrategy
{
    
    protected $annotation;
    
    public function __construct($annotation)
    {
        $this->annotation = $annotation;
    }


    /**
     * @param   Symfony\Component\DependencyInjection\ContainerInterface $container
     * @param   $service
     */
    abstract function execute(ContainerInterface $container, $service);
    
    
    /**
     * @param mixed $annotation
     * @return AbstractStrategy 
     */
    public static function fatory($annotation)
    {
        if(!is_object($annotation))
        {
            throw new \InvalidArgumentException(sprintf('Class "%s" does not exist.', $name));
        }
        
        $class      = self::getStrategyClass($annotation);
        $instance   = new $class($annotation);
        
        if(!($instance instanceof self))
        {
            throw new \InvalidArgumentException(sprintf('Class "%s" does not extends "%s".', $class, get_class()));
        }
        
        return $instance;
    }
    
    
    /**
     * @param   mixed $annotation
     * @return  string
     */
    private static function getAnnotationName($annotation)
    {
        $name = explode('\\', get_class($annotation));
        return $name[count($name)-1];
    }
    
    
    /**
     * @param mixed $annotation
     * @return string 
     */
    private static function getStrategyClass($annotation)
    {
        $anotationName  = self::getAnnotationName($annotation);
        $class          = __NAMESPACE__ ."\\" . $anotationName . 'Strategy';
        return $class;
    }
    
    
    /**
     * @param   mixed     $instance
     * @param   string    $property
     * @param   mixed     $value
     * @return  mixed
     */
    protected function setPropertyValue($instance,$property,$value) 
    {
        $reflection     = new \ReflectionObject($instance);
        $setterMethod   = 'set'.ucfirst($property);
        
        if($reflection->hasProperty($property))
        {
            $propertyObj = $reflection->getProperty($property);

            if($propertyObj->isPublic())
            {
                $instance->{$property} = $value;
            }
            else if($reflection->hasMethod($setterMethod))
            {
                $instance->{$setterMethod}($value);
            }
            else
            {
                $propertyObj->setAccessible(true);
                $propertyObj->setValue($instance, $value);
                $propertyObj->setAccessible(false);
            }
        }
        else if(get_class($instance) == 'stdClass')
        {
            $instance->{$property} = $value;
        }
        return $instance;
    }
}