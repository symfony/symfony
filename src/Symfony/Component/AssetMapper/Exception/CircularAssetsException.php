<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Exception;

use Symfony\Component\AssetMapper\MappedAsset;

/**
 * Thrown when a circular reference is detected while creating an asset.
 */
class CircularAssetsException extends RuntimeException
{
    public function __construct(private MappedAsset $mappedAsset, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns the asset that was being created when the circular reference was detected.
     *
     * This asset will not be fully initialized: it will be missing some
     * properties like digest and content.
     */
    public function getIncompleteMappedAsset(): MappedAsset
    {
        return $this->mappedAsset;
    }
}
