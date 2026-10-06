<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures;

use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class RecordingNameConverter implements NameConverterInterface
{
    /**
     * @var list<array{string, ?string, ?string, array<string, mixed>}>
     */
    public array $calls = [];

    public function normalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
    {
        $this->calls[] = [$propertyName, $class, $format, $context];

        return $propertyName;
    }

    public function denormalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string
    {
        return $propertyName;
    }
}
