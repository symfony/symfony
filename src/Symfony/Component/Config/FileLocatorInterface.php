<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config;

/**
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface FileLocatorInterface
{
    /**
     * Returns a full path for a given file name.
     *
     * @param mixed  $name        The file name to locate
     * @param string $currentPath The current path
     *
     * @return string The full path for the file
     *
     * @throws \InvalidArgumentException When file is not found
     */
    function locate($name, $currentPath = null, $first = true);
}
