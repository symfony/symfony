<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Attribute;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestHeaderValueResolver;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class MapRequestHeader extends ValueResolver
{
    /**
     * @param string|null  $name                       The name of the header parameter; if null, the name of the argument in the controller will be used
     * @param class-string $resolver                   The class name of the resolver to use
     * @param int          $validationFailedStatusCode The HTTP code to return if the validation fails
     */
    public function __construct(
        public readonly ?string $name = null,
        string $resolver = RequestHeaderValueResolver::class,
        public readonly int $validationFailedStatusCode = Response::HTTP_BAD_REQUEST,
    ) {
        parent::__construct($resolver);
    }
}
