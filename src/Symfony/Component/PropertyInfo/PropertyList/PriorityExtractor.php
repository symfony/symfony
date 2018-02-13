<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\PropertyList;

use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;

/**
 * PropertyListExtractor by priority.
 *
 * @author Andrey Samusev <andrey_simfi@list.ru>
 */
class PriorityExtractor implements PropertyListExtractorInterface
{
    /**
     * @var iterable|PropertyListExtractorInterface[]
     */
    private $extractors;

    /**
     * @param iterable|PropertyListExtractorInterface[] $extractors
     */
    public function __construct(iterable $extractors)
    {
        $this->extractors = $extractors;
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties($class, array $context = array())
    {
        $properties = null;
        foreach ($this->extractors as $extractor) {
            $properties = $extractor->getProperties($class, $context);
            if (null !== $properties) {
                break;
            }
        }

        return $properties;
    }
}
