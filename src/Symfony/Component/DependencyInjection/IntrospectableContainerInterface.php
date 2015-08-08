<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

/**
 * IntrospectableContainerInterface defines additional introspection functionality
 * for containers, allowing logic to be implemented based on a Container's state.
 *
 * @author Evan Villemez <evillemez@gmail.com>
 *
 * @deprecated Since version 3.0, to be removed in 4.0. Use ContainerInterface
 *             instead.
 */
interface IntrospectableContainerInterface extends ContainerInterface
{
}
