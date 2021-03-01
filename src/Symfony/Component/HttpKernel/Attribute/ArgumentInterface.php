<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Attribute;

trigger_deprecation('symfony/http-kernel', '5.3', 'The "%s" interface is deprecated.', ArgumentInterface::class);

/**
 * Marker interface for controller argument attributes.
 *
 * @deprecated since Symfony 5.3
 */
interface ArgumentInterface
{
}
