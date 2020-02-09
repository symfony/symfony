<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Routing\Loader\Configurator;

use Symfony\Bundle\FrameworkBundle\Routing\Loader\Configurator\Traits\AddTrait;
use Symfony\Component\Routing\Loader\Configurator\RouteConfigurator;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class GoneRouteConfigurator extends RouteConfigurator
{
    use AddTrait;

    /**
     * @param bool $permanent Whether the route is gone permanently
     *
     * @return $this
     */
    final public function permanent(bool $permanent = true)
    {
        return $this->defaults(['permanent' => $permanent]);
    }
}
