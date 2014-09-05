<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Config\Resource\Refresher;

use Symfony\Component\Config\Resource\Refresher\RefresherInterface;
use Symfony\Component\Config\Resource\MutableResourceInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @author Luc Vieillescazes <luc@vieillescazes.net>
 */
class ContainerAwareRefresher implements RefresherInterface
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function refresh(MutableResourceInterface $resource)
    {
        if ($resource instanceof ContainerAwareInterface) {
            $resource->setContainer($this->container);
        }
    }
}
