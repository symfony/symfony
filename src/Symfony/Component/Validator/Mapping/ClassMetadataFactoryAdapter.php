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
        $this->innerFactory = $innerFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataFor($value)
    {
        $class = is_object($value) ? get_class($value) : $value;
        set_error_handler(array($this, 'handleBC'));
        $metadata = $this->innerFactory->getClassMetadata($class);
        restore_error_handler();

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

        set_error_handler(array($this, 'handleBC'));
        $return = null !== $this->innerFactory->getClassMetadata($class);
        restore_error_handler();

        return $return;
    }

    /**
     * @deprecated This is used to keep BC until deprecated methods are removed
     */
    public function handleBC($errorNumber, $message, $file, $line, $context)
    {
        if ($errorNumber & E_USER_DEPRECATED) {
            return true;
        }

        return false;
    }
}
