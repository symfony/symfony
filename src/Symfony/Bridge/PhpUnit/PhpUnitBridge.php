<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit;

/**
 * Bootstrap class for the PhpUnitBridge.
 *
 * @author Christian Flothmann <christian.flothmann@xabbuh.de>
 */
final class PhpUnitBridge
{
    private function __construct()
    {
    }

    /**
     * Returns the actual path to the bootstrap file.
     *
     * @return string
     */
    public static function getBootstrapFilePath()
    {
        return __DIR__.'/bootstrap.php';
    }
}
