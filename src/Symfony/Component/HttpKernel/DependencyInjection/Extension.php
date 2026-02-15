<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DependencyInjection;

use Symfony\Component\DependencyInjection\Extension\Extension as BaseExtension;

trigger_deprecation('symfony/http-kernel', '8.1', 'The "%s" class is deprecated, use "%s" instead.', Extension::class, BaseExtension::class);

/**
 * Allow adding classes to the class cache.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 8.1; use Symfony\Component\DependencyInjection\Extension\Extension instead
 */
abstract class Extension extends BaseExtension
{
}
