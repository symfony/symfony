<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping;

use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Exception\NoSuchMetadataException;

/**
 * An adapter for exposing {@link ClassMetadataFactoryInterface} implementations
 * under the new {@link MetadataFactoryInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ClassMetadataFactoryAdapter implements MetadataFactoryInterface
{
    /**
     * @var ClassMetadataFactoryInterface
     */
    private $innerFactory;

    public function __construct(ClassMetadataFactoryInterface $innerFactory)
    {
        trigger_error(sprintf('ClassMetadataFactoryInterface is deprecated since version 2.1 and will be removed in 2.3. Implement MetadataFactoryInterface instead on %s.', get_class($innerFactory)), E_USER_DEPRECATED);

        $this->innerFactory = $innerFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataFor($value)
    {
        $class = is_object($value) ? get_class($value) : $value;
        $metadata = $this->innerFactory->getClassMetadata($class);

        if (null === $metadata) {
            throw new NoSuchMetadataException('No metadata exists for class '. $class);
        }

        return $metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function hasMetadataFor($value)
    {
        $class = is_object($value) ? get_class($value) : $value;

        $return = null !== $this->innerFactory->getClassMetadata($class);

        return $return;
    }
}
