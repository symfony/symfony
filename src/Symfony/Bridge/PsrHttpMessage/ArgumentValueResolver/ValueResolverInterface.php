<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PsrHttpMessage\ArgumentValueResolver;

use Symfony\Component\HttpKernel\Controller\ValueResolverInterface as BaseValueResolverInterface;

if (interface_exists(BaseValueResolverInterface::class)) {
    /** @internal */
    interface ValueResolverInterface extends BaseValueResolverInterface
    {
    }
} else {
    /** @internal */
    interface ValueResolverInterface
    {
    }
}
