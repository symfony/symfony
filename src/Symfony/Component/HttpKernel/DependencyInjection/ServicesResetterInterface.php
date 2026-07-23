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

use Symfony\Component\DependencyInjection\ServicesResetterInterface as BaseServicesResetterInterface;

trigger_deprecation('symfony/http-kernel', '8.1', 'The "%s" interface is deprecated, use "%s" from the DependencyInjection component instead.', ServicesResetterInterface::class, BaseServicesResetterInterface::class);

/**
 * Resets provided services.
 *
 * @deprecated since Symfony 8.1, use ServicesResetterInterface from the DependencyInjection component instead
 */
interface ServicesResetterInterface extends BaseServicesResetterInterface
{
}
