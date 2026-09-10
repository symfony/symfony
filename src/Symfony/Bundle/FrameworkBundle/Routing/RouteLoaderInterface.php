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

use Symfony\Component\Routing\Attribute\AsRouteLoader;

trigger_deprecation('symfony/framework-bundle', '8.2', 'The "%s" interface is deprecated, use the "#[%s]" attribute instead.', RouteLoaderInterface::class, AsRouteLoader::class);

/**
 * Marker interface for service route loaders.
 *
 * @deprecated since Symfony 8.2, use the #[AsRouteLoader] attribute instead
 */
interface RouteLoaderInterface
{
}
