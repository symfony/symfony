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
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class MapRequestHeader extends ValueResolver
{
    public ArgumentMetadata $metadata;

    public function __construct(
        public readonly string|array|null $name = null,
        string $resolver = RequestHeaderValueResolver::class,
        public readonly int $validationFailedStatusCode = Response::HTTP_UNPROCESSABLE_ENTITY,
    ) {
        parent::__construct($resolver);
    }
}
