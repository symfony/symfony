<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Validator\Constraints;

use Symfony\Component\Security\Core\Validator\Constraints\UserPassword as BaseUserPassword;

/**
 * This class defines a constraint to validate the current logged-in user's
 * password.
 *
 * @Annotation
 *
 * @author Hugo Hamon <webmaster@apprendre-php.com>
 */
class UserPassword extends BaseUserPassword
{
    public $service = 'security.validator.user_password';

    public function validatedBy()
    {
        return $this->service;
    }
}
