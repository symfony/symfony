<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Routing;

use Symfony\Component\Routing\Loader\AttributeRouteControllerLoader as BaseAttributeRouteControllerLoader;

trigger_deprecation('symfony/framework-bundle', '8.2', 'The "%s" class is deprecated, use "%s" instead.', AttributeRouteControllerLoader::class, BaseAttributeRouteControllerLoader::class);

/**
 * @deprecated since Symfony 8.2, use Symfony\Component\Routing\Loader\AttributeRouteControllerLoader instead
 */
class AttributeRouteControllerLoader extends BaseAttributeRouteControllerLoader
{
}
