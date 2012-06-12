<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Exception;

/**
 * This exception is thrown when the user has exceeded the allowed number of sessions, and the
 * ConcurrentSessionControlStrategy is set to limit the number by disallowing opening new sessions.
 * (By default, the ConcurrentSessionControlStrategy will expire the user's oldest existing session)
 *
 * @author Stefan Paschke <stefan.paschke@gmail.com>
 */
class MaxSessionsExceededException extends AuthenticationException
{
}
