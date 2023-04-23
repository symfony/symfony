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

use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestPayloadValueResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Validator\Constraints\GroupSequence;

/**
 * Controller parameter tag to map the request content to typed object and validate it.
 *
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class MapRequestPayload extends ValueResolver
{
    public ArgumentMetadata $metadata;

    public function __construct(
        public readonly array|string|null $acceptFormat = null,
        public readonly array $serializationContext = [],
        public readonly string|GroupSequence|array|null $validationGroups = null,
        string $resolver = RequestPayloadValueResolver::class,
    ) {
        parent::__construct($resolver);
    }
}
