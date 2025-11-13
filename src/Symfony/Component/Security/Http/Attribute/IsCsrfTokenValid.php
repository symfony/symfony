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
    public const SOURCE_PAYLOAD = 0b0001;
    public const SOURCE_QUERY = 0b0010;
    public const SOURCE_HEADER = 0b0100;

    public function __construct(
        /**
         * Sets the id, or an Expression evaluated to the id, used when generating the token.
         */
        public string|Expression $id,

        /**
         * Sets the key of the request that contains the actual token value that should be validated.
         */
        public string $tokenKey = '_token',

        /**
         * Sets the available http methods that can be used to validate the token.
         * If not set, the token will be validated for all methods.
         */
        public array|string $methods = [],

        /**
         * Sets the source targeted to read the tokenKey.
         *
         * @var int-mask-of<self::SOURCE_*>
         */
        public int $tokenSource = self::SOURCE_PAYLOAD,
    ) {
    }
}
