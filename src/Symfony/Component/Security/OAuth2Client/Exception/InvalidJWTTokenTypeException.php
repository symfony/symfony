<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\OAuth2Client\Exception;

/**
 * Represent an error linked to the usage of an invalid JWT token.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class InvalidJWTTokenTypeException extends \LogicException
{
}
