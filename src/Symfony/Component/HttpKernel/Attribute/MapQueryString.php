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

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestPayloadValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Validator\Constraints\GroupSequence;

/**
 * Controller parameter tag to map the query string of the request to typed object and validate it.
 *
 * @psalm-import-type GroupResolver from RequestPayloadValueResolver
 *
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class MapQueryString extends ValueResolver
{
    public ArgumentMetadata $metadata;

    /**
     * @param array<string, mixed>                                             $serializationContext       The serialization context to use when deserializing the query string
     * @param string|Expression|GroupSequence|GroupResolver|array<string>|null $validationGroups           The validation groups to use when validating the query string mapping
     * @param class-string                                                     $resolver                   The class name of the resolver to use
     * @param int                                                              $validationFailedStatusCode The HTTP code to return if the validation fails
     * @param bool                                                             $skipInvalidFields          Whether to ignore query parameters that cannot be denormalized and fall back to the mapped object's default values instead of failing
     */
    public function __construct(
        public readonly array $serializationContext = [],
        public readonly string|Expression|GroupSequence|\Closure|array|null $validationGroups = null,
        string $resolver = RequestPayloadValueResolver::class,
        public readonly int $validationFailedStatusCode = Response::HTTP_NOT_FOUND,
        public readonly ?string $key = null,
        public bool $mapWhenEmpty = false,
        public readonly bool $skipInvalidFields = false,
    ) {
        parent::__construct($resolver);
    }
}
