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

use Symfony\Component\Form\Type\Loader\TypeLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ContainerAwareTypeLoader implements TypeLoaderInterface
{
    private $container;

    private $serviceIds;

    public function __construct(ContainerInterface $container, array $serviceIds)
    {
        $this->container = $container;
        $this->serviceIds = $serviceIds;
    }

    public function getType($identifier)
    {
        // TODO check whether identifier exists

        return $this->container->get($this->serviceIds[$identifier]);
    }

    public function hasType($identifier)
    {
        return isset($this->serviceIds[$identifier]);
    }
}