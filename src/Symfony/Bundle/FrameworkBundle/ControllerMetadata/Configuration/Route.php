<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\ControllerMetadata\Configuration;

use Symfony\Component\Routing\Annotation\Route as BaseRoute;

/**
 * @author Kris Wallsmith <kris@symfony.com>
 * @Annotation
 */
class Route extends BaseRoute
{
    protected $service;

    public function setService($service)
    {
        // avoid a BC notice in case of @Route(service="") with sf ^2.7
        if (null === $this->getPath()) {
            $this->setPath('');
        }
        $this->service = $service;
    }

    public function getService()
    {
        return $this->service;
    }

    /**
     * Multiple route annotations are allowed.
     *
     * @return bool
     *
     * @see ConfigurationInterface
     */
    public function allowArray()
    {
        return true;
    }
}
