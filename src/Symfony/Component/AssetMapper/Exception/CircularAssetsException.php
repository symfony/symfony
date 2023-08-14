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

/**
 * Thrown when a circular reference is detected while creating an asset.
 */
class CircularAssetsException extends RuntimeException
{
}
