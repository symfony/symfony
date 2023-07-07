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

interface PasswordPolicyInterface
{
    /**
     * @throws AuthenticationException
     */
    public function verify(#[\SensitiveParameter] string $plaintextPassword): bool;
}
