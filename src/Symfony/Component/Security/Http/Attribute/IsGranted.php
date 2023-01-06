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

/**
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final class IsGranted
{
    public function __construct(
        /**
         * Sets the first argument that will be passed to isGranted().
         */
        public string|Expression $attribute,

        /**
         * Sets the second argument passed to isGranted().
         *
         * @var array<string|Expression>|string|Expression|null
         */
        public array|string|Expression|null $subject = null,

        /**
         * The message of the exception - has a nice default if not set.
         */
        public ?string $message = null,

        /**
         * If set, will throw HttpKernel's HttpException with the given $statusCode.
         * If null, Security\Core's AccessDeniedException will be used.
         */
        public ?int $statusCode = null,
    ) {
    }
}
