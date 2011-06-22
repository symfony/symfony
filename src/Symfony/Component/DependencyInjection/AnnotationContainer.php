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

use Doctrine\Common\Annotations\Reader,
    Symfony\Component\DependencyInjection\Annotation\Autoware,
    Symfony\Component\DependencyInjection\AnnotationCollection,
    Symfony\Component\DependencyInjection\Annotation\Strategy\AbstractStrategy,
    Doctrine\Common\Annotations\AnnotationReader;

/**
 * AnnotationContainer is a dependency injection container.
 *
 * It gives access to object instances (services) and inject dependencies.
 *
 * @author Fabio B. Silva <fabio.bat.silva@gmail.com>
 */
class AnnotationContainer extends Container
{

    /**
     * @var array 
     */
    private $annotations = array();
    
    /**
     * @var array 
     */
    private $configured  = array();
    
    
    
    /**
     * Gets a service.
     *
     * If a service is both defined through a set() method and
     * with a set*Service() method, the former has always precedence.
     *
     * @param  string  $id              The service identifier
     * @param  integer $invalidBehavior The behavior when the service does not exist
     *
     * @return object The associated service
     *
     * @throws \InvalidArgumentException if the service is not defined
     *
     * @see Reference
     */
    public function get($id, $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE)
    {
        $service = parent::get($id, $invalidBehavior);
        
        if(is_object($service) && !$this->isConfigured($id))
        {
            $this->configure($service);
            
            $this->configured[$id] = true;
        }
        
        return $service;
    }
    
    
     /**
     * @param	mixed
     * @return 	mixed
     */
    public function configure($service) 
    {
        if(is_object($service))
        {
            $annotations = $this->getAnnotations($service);
            foreach ($annotations as $annotation) 
            {
                AbstractStrategy::fatory($annotation)->execute($this, $service);
            }
        }

        return $service;
    }
    
    private function isConfigured($id) 
    {
        return isset($this->configured[$id]) && $this->configured[$id] === true;
    }

    
    /**
     * @param  mixed
     * @return Symfony\Component\DependencyInjection\AnnotationCollection 
     */
    private function loadAnnotations($instance)
    {
        $reader  = new AnnotationReader();
        $loader  = new AnnotationLoader($reader, get_class($instance));
        return $loader->load();
    }
    
    
    /**
     * @param  mixed
     * @return Symfony\Component\DependencyInjection\AnnotationCollection 
     */
    public function getAnnotations($instance)
    {
        if(!is_object($instance))
        {
            throw new \InvalidArgumentException();
        }
        
        $class = (string) get_class($instance);
        if(!isset($this->annotations[$class]) || !($this->annotations[$class] instanceof AnnotationCollection))
        {
            $this->annotations[$class] = $this->loadAnnotations($instance);
        }
                
        return $this->annotations[$class];
    }
    

}
