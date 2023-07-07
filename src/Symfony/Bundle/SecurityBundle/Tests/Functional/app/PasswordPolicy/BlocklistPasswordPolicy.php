<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\app\PasswordPolicy;

use Symfony\Component\Security\Http\Authenticator\Passport\Policy\PasswordPolicyInterface;

final class BlocklistPasswordPolicy implements PasswordPolicyInterface
{
    public function verify(#[\SensitiveParameter] string $plaintextPassword): bool
    {
        return 'foo' !== $plaintextPassword;
    }
}
