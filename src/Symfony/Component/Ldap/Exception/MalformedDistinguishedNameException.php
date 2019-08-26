<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Exception;

/**
 * MalformedDistinguishedNameException is thrown in case of an malformed DN.
 *
 * @author Dominic Tubach <dominic.tubach@to.com>
 */
class MalformedDistinguishedNameException extends \RuntimeException implements ExceptionInterface
{
}
