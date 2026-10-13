<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess;

use Symfony\Component\PropertyInfo\PropertyReadInfo;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyWriteInfo;
use Symfony\Component\PropertyInfo\PropertyWriteInfoExtractorInterface;

final class InfoExtractorSpy implements PropertyReadInfoExtractorInterface, PropertyWriteInfoExtractorInterface
{
    public array $lookups = [];

    public function __construct(
        private readonly PropertyReadInfoExtractorInterface&PropertyWriteInfoExtractorInterface $extractor,
    ) {
    }

    public function getReadInfo(string $class, string $property, array $context = []): ?PropertyReadInfo
    {
        $this->lookups[] = "read $class::$property";

        return $this->extractor->getReadInfo($class, $property, $context);
    }

    public function getWriteInfo(string $class, string $property, array $context = []): ?PropertyWriteInfo
    {
        $this->lookups[] = "write $class::$property";

        return $this->extractor->getWriteInfo($class, $property, $context);
    }
}
