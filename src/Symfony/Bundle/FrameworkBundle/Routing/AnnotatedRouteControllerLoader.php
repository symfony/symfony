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

trigger_deprecation('symfony/framework-bundle', '6.4', 'The "%s" class is deprecated, use "%s" instead.', AnnotatedRouteControllerLoader::class, AttributeRouteControllerLoader::class);

class_exists(AttributeRouteControllerLoader::class);

if (false) {
    /**
     * @deprecated since Symfony 6.4, to be removed in 7.0, use {@link AttributeRouteControllerLoader} instead
     */
    class AnnotatedRouteControllerLoader extends AttributeRouteControllerLoader
    {
    }
}
