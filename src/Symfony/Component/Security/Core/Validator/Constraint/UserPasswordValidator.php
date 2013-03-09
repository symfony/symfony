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

use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPasswordValidator as BaseUserPasswordValidator;

/**
 * @deprecated Deprecated since version 2.2, to be removed in 2.3.
 */
class UserPasswordValidator extends BaseUserPasswordValidator
{
    public function __construct(SecurityContextInterface $securityContext, EncoderFactoryInterface $encoderFactory)
    {
        trigger_error('UserPasswordValidator class in Symfony\Component\Security\Core\Validator\Constraint namespace is deprecated since version 2.2 and will be removed in 2.3. Use the Symfony\Component\Security\Core\Validator\Constraints\UserPasswordValidator class instead.', E_USER_DEPRECATED);

        parent::__construct($securityContext, $encoderFactory);
    }
}
