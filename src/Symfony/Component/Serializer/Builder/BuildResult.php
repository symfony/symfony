<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Builder;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 7.1
 */
class BuildResult
{
    public function __construct(
        // The full file location where the class is stored
        public readonly string $filePath,
        // Just the class name
        public readonly string $className,
        // Class name with namespace
        public readonly string $classNs,
    ) {
    }

    public function loadClass()
    {
        require_once $this->filePath;
    }
}
