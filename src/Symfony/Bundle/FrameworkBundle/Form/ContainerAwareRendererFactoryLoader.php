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

use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Renderer\Loader\FormRendererFactoryLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ContainerAwareRendererFactoryLoader implements FormRendererFactoryLoaderInterface
{
    private $container;

    private $serviceIds;

    public function __construct(ContainerInterface $container, array $serviceIds)
    {
        $this->container = $container;
        $this->serviceIds = $serviceIds;
    }

    public function getRendererFactory($name)
    {
        if (!isset($this->serviceIds[$name])) {
            throw new FormException(sprintf('No renderer factory exists with name "%s"', $name));
        }

        return $this->container->get($this->serviceIds[$name]);
    }

    public function hasRendererFactory($name)
    {
        return isset($this->serviceIds[$name]);
    }
}