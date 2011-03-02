<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Form;

use Symfony\Component\Form\Config\Loader\ConfigLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ContainerAwareConfigLoader implements ConfigLoaderInterface
{
    private $container;

    private $serviceIds;

    public function __construct(ContainerInterface $container, array $serviceIds)
    {
        $this->container = $container;
        $this->serviceIds = $serviceIds;
    }

    public function getConfig($identifier)
    {
        // TODO check whether identifier exists

        return $this->container->get($this->serviceIds[$identifier]);
    }

    public function hasConfig($identifier)
    {
        return isset($this->serviceIds[$identifier]);
    }
}