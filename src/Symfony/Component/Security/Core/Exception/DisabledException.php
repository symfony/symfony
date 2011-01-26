<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Exception;

/**
 * DisabledException is thrown when the user account is disabled.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DisabledException extends AccountStatusException
{
}
