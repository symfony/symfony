<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Security\EntryPoint;

use Symfony\Component\Security\Exception\AuthenticationException;
use Symfony\Component\Security\Authentication\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\LoggerInterface;

/**
 * NonceExpiredException is thrown when an authentication is rejected because
 * the digest nonce has expired.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class NonceExpiredException extends AuthenticationException
{
}
