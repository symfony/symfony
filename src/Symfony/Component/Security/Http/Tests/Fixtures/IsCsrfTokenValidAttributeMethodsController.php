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

use Symfony\Component\ExpressionLanguage\Expression;
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

    #[IsCsrfTokenValid(new Expression('"foo_" ~ args.id'))]
    public function withCustomExpressionId(string $id)
    {
    }

    #[IsCsrfTokenValid(new Expression('"foo_" ~ args.slug'))]
    public function withInvalidExpressionId(string $id)
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

    #[IsCsrfTokenValid('foo', methods: 'DELETE')]
    public function withDeleteMethod()
    {
    }

    #[IsCsrfTokenValid('foo', methods: ['GET', 'POST'])]
    public function withGetOrPostMethod()
    {
    }

    #[IsCsrfTokenValid('foo', tokenKey: 'invalid_token_key', methods: ['POST'])]
    public function withPostMethodAndInvalidTokenKey()
    {
    }
}
