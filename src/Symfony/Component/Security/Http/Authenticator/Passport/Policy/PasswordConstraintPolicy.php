<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Passport\Policy;

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PasswordConstraintPolicy implements PasswordPolicyInterface
{
    /**
     * @param array<Constraint> $constraints
     */
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly array $constraints = []
    ) {
    }

    /**
     * @throws AuthenticationException
     */
    public function verify(#[\SensitiveParameter] string $plaintextPassword): bool
    {
        $errors = $this->validator->validate($plaintextPassword, $this->constraints);

        return !$errors->count();
    }
}
