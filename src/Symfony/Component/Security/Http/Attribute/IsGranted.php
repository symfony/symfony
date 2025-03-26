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
use Symfony\Component\HttpFoundation\Request;

/**
 * Checks if user has permission to access to some resource using security roles and voters.
 *
 * @see https://symfony.com/doc/current/security.html#roles
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final class IsGranted
{
    /**
     * @param string|Expression|\Closure(IsGrantedContext, mixed $subject):bool $attribute The attribute that will be checked against a given authentication token and optional subject
     * @param array|string|Expression|\Closure(array<string,mixed>, Request):mixed|null $subject An optional subject - e.g. the current object being voted on
     * @param string|null $message       A custom message when access is not granted
     * @param int|null    $statusCode    If set, will throw HttpKernel's HttpException with the given $statusCode; if null, Security\Core's AccessDeniedException will be used
     * @param int|null    $exceptionCode If set, will add the exception code to thrown exception
     */
    public function __construct(
        public string|Expression|\Closure $attribute,
        public array|string|Expression|\Closure|null $subject = null,
        public ?string $message = null,
        public ?int $statusCode = null,
        public ?int $exceptionCode = null,
    ) {
    }
}
