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

use Symfony\Component\DependencyInjection\ServicesResetter as BaseServicesResetter;

trigger_deprecation('symfony/http-kernel', '8.1', 'The "%s" class is deprecated, use "%s" from the DependencyInjection component instead.', ServicesResetter::class, BaseServicesResetter::class);

/**
 * Resets provided services.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @deprecated since Symfony 8.1, use ServicesResetter from the DependencyInjection component instead
 */
final class ServicesResetter extends BaseServicesResetter implements ServicesResetterInterface
{
}
