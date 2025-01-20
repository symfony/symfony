<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Attribute;

use Symfony\Component\ExpressionLanguage\Expression;

#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final class IsCsrfTokenValid
{
    public function __construct(
        /**
         * Sets the id, or an Expression evaluated to the id, used when generating the token.
         */
        public string|Expression $id,

        /**
         * Sets the key of the request that contains the actual token value that should be validated.
         */
        public ?string $tokenKey = '_token',
    ) {
    }
}
