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
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator as BaseRoutingConfigurator;

class RoutingConfigurator extends BaseRoutingConfigurator
{
    use AddTrait;
}
