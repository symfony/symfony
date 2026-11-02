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
 * Thrown when the directory of an issuer cannot be fetched, or announces no key this origin can verify a token with.
 */
class IssuerDirectoryException extends \RuntimeException
{
}
