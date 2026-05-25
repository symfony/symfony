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

use Symfony\Component\DependencyInjection\Compiler\ResettableServicePass as BaseResettableServicePass;

trigger_deprecation('symfony/http-kernel', '8.1', 'The "%s" class is deprecated, use "%s" from the DependencyInjection component instead.', ResettableServicePass::class, BaseResettableServicePass::class);

/**
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @deprecated since Symfony 8.1, use ResettableServicePass from the DependencyInjection component instead
 */
class ResettableServicePass extends BaseResettableServicePass
{
}
