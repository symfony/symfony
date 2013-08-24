<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition\Exception;

/**
 * A very general exception which can be thrown whenever non of the more specific
 * exceptions is suitable.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @since v2.0.0
 */
class InvalidConfigurationException extends Exception
{
    private $path;

    /**
     * @since v2.0.0
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * @since v2.0.0
     */
    public function getPath()
    {
        return $this->path;
    }
}
