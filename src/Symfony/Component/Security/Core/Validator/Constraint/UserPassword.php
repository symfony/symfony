<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Validator\Constraint;

use Symfony\Component\Security\Core\Validator\Constraints\UserPassword as BaseUserPassword;

/**
 * @Annotation
 *
 * @deprecated Deprecated since version 2.2, to be removed in 2.3.
 */
class UserPassword extends BaseUserPassword
{
    public function __construct($options = null)
    {
        trigger_error('UserPassword class in Symfony\Component\Security\Core\Validator\Constraint namespace is deprecated since version 2.2 and will be removed in 2.3. Use the Symfony\Component\Security\Core\Validator\Constraints\UserPassword class instead.', E_USER_DEPRECATED);

        parent::__construct($options);
    }
}
