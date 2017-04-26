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
 * Abstract Annotation class
 *
 * @author Fabio B. Silva <fabio.bat.silva@gmail.com>
 */
abstract class Annotation
{

    /**
     * @param string 
     */
    public function setProperty($property)
    {
        $this->property = $property;
    }
    
    
    /**
     * @return string 
     */
    public function getProperty()
    {
        return $this->property;
    }


    /**
     * @param array $options 
     */
    protected function setOptions(array $options)
    {
        foreach ($options as $key => $value)
        {
            $method = 'set' . $key;
            if (!method_exists($this, $method))
            {
                throw new \BadMethodCallException(sprintf("Unknown property '%s' on annotation '%s'.", $key, get_class($this)));
            }
            $this->{$method}($value);
        }
    }

}