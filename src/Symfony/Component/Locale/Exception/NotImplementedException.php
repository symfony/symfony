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

@trigger_error('The '.__NAMESPACE__.'\NotImplementedException class is deprecated since Symfony 2.3 and will be removed in 3.0. Use the Symfony\Component\Intl\Exception\NotImplementedException class instead.', E_USER_DEPRECATED);

use Symfony\Component\Intl\Exception\NotImplementedException as BaseNotImplementedException;

/**
 * Alias of {@link \Symfony\Component\Intl\Exception\NotImplementedException}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since version 2.3, to be removed in 3.0.
 *             Use {@link \Symfony\Component\Intl\Exception\NotImplementedException}
 *             instead.
 */
class NotImplementedException extends BaseNotImplementedException
{
}
