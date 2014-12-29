<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Exception;

trigger_error('The '.__NAMESPACE__.'\AlreadyBoundException class is deprecated since version 2.3 and will be removed in 3.0. Use the Symfony\Component\Form\Exception\AlreadySubmittedException class instead.', E_USER_DEPRECATED);

/**
 * Alias of {@link AlreadySubmittedException}.
 *
 * @deprecated since version 2.3, to be removed in 3.0.
 *             Use {@link AlreadySubmittedException} instead.
 */
class AlreadyBoundException extends LogicException
{
}
