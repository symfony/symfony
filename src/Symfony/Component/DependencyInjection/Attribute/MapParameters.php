<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Attribute;

/**
 * Maps parameters from the container to a DTO class.
 *
 * Classes annotated with this attribute will have their properties automatically populated from a container parameter path.
 *
 * @author Ayyoub AFW-ALLAH <ayyoub.afwallah@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class MapParameters
{
    /**
     * @param string $path Container parameter path (e.g., 'S3.Archive' maps from parameters.S3.Archive)
     */
    public function __construct(
        public readonly string $path,
    ) {
    }
}
