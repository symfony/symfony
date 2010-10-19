<?php

namespace Symfony\Component\Security\Exception;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * InsufficientAuthenticationException is thrown if the user credentials are not sufficiently trusted.
 *
 * This is the case when a user is anonymous and the resource to be displayed has an access role.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class InsufficientAuthenticationException extends AuthenticationException
{
}
