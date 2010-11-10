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
 * DisabledException is thrown when the user account is disabled.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DisabledException extends AccountStatusException
{
}
