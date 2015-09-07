<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Resource;

use Symfony\Component\Config\MetadataValidatorInterface;

/**
 * Validator for metadata implementing the ResourceInterface.
 *
 * @author Benjamin Klotz <bk@webfactory.de>
 */
class ResourceValidator implements MetadataValidatorInterface
{
    public function supports($metadata)
    {
        return $metadata instanceof ResourceInterface;
    }

    public function isFresh($metadata, $timestamp)
    {
        return $metadata->isFresh($timestamp);
    }
}
