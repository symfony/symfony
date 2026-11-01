<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\PrivacyPass\Exception;

/**
 * Thrown when a Privacy Pass token is malformed, does not answer a challenge of this origin or carries an invalid authenticator.
 *
 * An origin answers it with a fresh challenge: the client cannot tell which check failed.
 */
class InvalidPrivateTokenException extends \RuntimeException
{
}
