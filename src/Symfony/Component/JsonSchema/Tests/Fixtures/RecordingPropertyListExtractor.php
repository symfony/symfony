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

use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;

class RecordingPropertyListExtractor implements PropertyListExtractorInterface
{
    /**
     * @var list<array<string, mixed>>
     */
    public array $contexts = [];

    public function getProperties(string $class, array $context = []): ?array
    {
        $this->contexts[] = $context;

        return (new ReflectionExtractor())->getProperties($class, $context);
    }
}
