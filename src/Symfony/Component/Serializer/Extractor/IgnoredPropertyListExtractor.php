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
 * Remove properties given a ignore list of attributes in the context.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
final class IgnoredPropertyListExtractor implements PropertyListExtractorInterface
{
    public const ATTRIBUTES = 'ignored_attributes';

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

        $ignored = $context[self::ATTRIBUTES] ?? null;

        if (null === $ignored) {
            return $properties;
        }

        return array_diff($properties, $ignored);
    }
}
