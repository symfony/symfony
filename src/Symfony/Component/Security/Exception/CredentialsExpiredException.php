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
 * CredentialsExpiredException is thrown when the user account credentials have expired.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class CredentialsExpiredException extends AccountStatusException
{
}
