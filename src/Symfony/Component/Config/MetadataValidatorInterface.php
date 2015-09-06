<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config;

/**
 * Interface for MetadataValidators.
 *
 * When a ConfigCache instance is checked for freshness, all its associated
 * metadata resources are passed to MetadataValidators. The MetadataValidators
 * can then inspect the resources and decide whether the cache can be considered
 * fresh or not.
 *
 * @author Benjamin Klotz <bk@webfactory.de>
 */
interface MetadataValidatorInterface
{
    /**
     * Queries the MetadataValidator whether it can validate a given
     * resource or not.
     *
     * @param object $metadata The resource to be checked for freshness
     *
     * @return bool True if the MetadataValidator can handle this resource type, false if not
     */
    public function supports($metadata);

    /**
     * Validates the resource.
     *
     * @param object $metadata  The resource to be validated.
     * @param int    $timestamp The timestamp at which the cache associated with this resource was created.
     *
     * @return bool True if the resource has not changed since the given timestamp, false otherwise.
     */
    public function isFresh($metadata, $timestamp);

}
