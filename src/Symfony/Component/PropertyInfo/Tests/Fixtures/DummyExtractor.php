<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

use Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyDescriptionExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyInitializableExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DummyExtractor implements PropertyListExtractorInterface, PropertyDescriptionExtractorInterface, PropertyTypeExtractorInterface, PropertyAccessExtractorInterface, PropertyInitializableExtractorInterface
{
    /**
     * {@inheritdoc}
     */
    public function getShortDescription($class, $property, array $context = []): ?string
    {
        return 'short';
    }

    /**
     * {@inheritdoc}
     */
    public function getLongDescription($class, $property, array $context = []): ?string
    {
        return 'long';
    }

    /**
     * {@inheritdoc}
     */
    public function getTypes($class, $property, array $context = []): ?array
    {
        return [new Type(Type::BUILTIN_TYPE_INT)];
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable($class, $property, array $context = []): ?bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable($class, $property, array $context = []): ?bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties($class, array $context = []): ?array
    {
        return ['a', 'b'];
    }

    /**
     * {@inheritdoc}
     */
    public function isInitializable(string $class, string $property, array $context = []): ?bool
    {
        return true;
    }
}
