<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Fixtures;

use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

class IsCsrfTokenValidAttributeMethodsController
{
    public function noAttribute()
    {
    }

    #[IsCsrfTokenValid('foo')]
    public function withDefaultTokenKey()
    {
    }

    #[IsCsrfTokenValid('foo', tokenKey: 'my_token_key')]
    public function withCustomTokenKey()
    {
    }

    #[IsCsrfTokenValid('foo', tokenKey: 'invalid_token_key')]
    public function withInvalidTokenKey()
    {
    }
}
