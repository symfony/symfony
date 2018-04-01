<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Ldap\Exception;

/**
 * LdapException is throw if php ldap module is not loaded.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class LdapException extends \RuntimeException implements ExceptionInterface
{
}
