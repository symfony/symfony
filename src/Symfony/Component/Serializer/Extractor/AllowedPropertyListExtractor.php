<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Extractor;

use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;

/**
 * Allow properties given an allowed list of properties in the context.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
final class AllowedPropertyListExtractor implements PropertyListExtractorInterface
{
    public const ATTRIBUTES = 'attributes';

    private $extractor;

    public function __construct(PropertyListExtractorInterface $extractor)
    {
        $this->extractor = $extractor;
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties($class, array $context = [])
    {
        $properties = $this->extractor->getProperties($class, $context);

        if (null === $properties) {
            return null;
        }

        $allowed = $context[self::ATTRIBUTES] ?? null;

        if (null === $allowed) {
            return $properties;
        }

        return array_intersect($properties, $allowed);
    }
}
