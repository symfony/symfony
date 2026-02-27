<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ServiceLoadedValue;

use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\TransformCallableInterface;

/**
 * @implements TransformCallableInterface<object,object>
 */
class ServiceLoadedValueTransformer implements TransformCallableInterface
{
    public function __construct(private readonly LoadedValueService $serviceLoadedValue, private readonly ObjectMapperMetadataFactoryInterface $metadata)
    {
    }

    public function __invoke(mixed $value, object $source, ?object $target): mixed
    {
        $metadata = $this->metadata->create($value);

        if (\count($metadata) !== 1) {
            throw new \LogicException('Exactly one metadata should be returned.');
        }
        if ($metadata[0]->target !== LoadedValue::class) {
            throw new \LogicException('The target should be '.LoadedValue::class.'.');
        }

        return $this->serviceLoadedValue->get();
    }
}
