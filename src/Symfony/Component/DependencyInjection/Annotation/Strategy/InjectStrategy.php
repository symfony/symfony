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

use Symfony\Component\DependencyInjection\Annotation\Inject;
use Symfony\Component\DependencyInjection\Annotation\Strategy\AbstractStrategy;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Annotation class for @Inject().
 *
 * @author Fabio B. Silva <fabio.bat.silva@gmail.com>
 */
class InjectStrategy extends AbstractStrategy
{
    
    /**
     * @param   Symfony\Component\DependencyInjection\ContainerInterface $container
     * @param   $service
     */
    public function execute(ContainerInterface $container, $service)
    {
       if($this->annotation instanceof Inject)
       {
            $property   = $this->annotation->getProperty();
            $source     = $this->annotation->getSource();
            $value      = $container->get($source);

            $this->setPropertyValue($service, $property, $value);
       }
    }
    
}