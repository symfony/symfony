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
use Symfony\Component\Serializer\Context\ChildContextBuilderInterface;

/**
 * Remove properties given a ignore list of attributes in the context.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 *
 * @experimental in 4.3
 */
final class IgnoredPropertyListExtractor implements PropertyListExtractorInterface, ChildContextBuilderInterface
{
    use DecorateChildContextBuilderTrait;

    public const ATTRIBUTES = 'ignored_attributes';

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
