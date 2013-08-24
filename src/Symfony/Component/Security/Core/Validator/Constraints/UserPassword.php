<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 *
 * @since v2.2.0
 */
class UserPassword extends Constraint
{
    public $message = 'This value should be the user current password.';
    public $service = 'security.validator.user_password';

    /**
     * @since v2.2.0
     */
    public function validatedBy()
    {
        return $this->service;
    }
}
