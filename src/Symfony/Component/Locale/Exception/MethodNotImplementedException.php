<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Locale\Exception;

use Symfony\Component\Intl\Exception\MethodNotImplementedException as BaseMethodNotImplementedException;

/**
 * Alias of {@link \Symfony\Component\Intl\Exception\MethodNotImplementedException}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.3, to be removed in 3.0. Use
 *             {@link \Symfony\Component\Intl\Exception\MethodNotImplementedException}
 *             instead.
 */
class MethodNotImplementedException extends BaseMethodNotImplementedException
{
}
