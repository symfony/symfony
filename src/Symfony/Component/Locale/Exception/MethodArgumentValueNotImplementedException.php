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

use Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException as BaseMethodArgumentValueNotImplementedException;

/**
 * Alias of {@link \Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.3, to be removed in 3.0. Use
 *             {@link \Symfony\Component\Intl\Exception\MethodArgumentValueNotImplementedException}
 *             instead.
 */
class MethodArgumentValueNotImplementedException extends BaseMethodArgumentValueNotImplementedException
{
}
