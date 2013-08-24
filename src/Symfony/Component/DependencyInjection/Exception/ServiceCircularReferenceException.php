<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Exception;

/**
 * This exception is thrown when a circular reference is detected.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @since v2.0.0
 */
class ServiceCircularReferenceException extends RuntimeException
{
    private $serviceId;
    private $path;

    /**
     * @since v2.3.0
     */
    public function __construct($serviceId, array $path, \Exception $previous = null)
    {
        parent::__construct(sprintf('Circular reference detected for service "%s", path: "%s".', $serviceId, implode(' -> ', $path)), 0, $previous);

        $this->serviceId = $serviceId;
        $this->path = $path;
    }

    /**
     * @since v2.0.0
     */
    public function getServiceId()
    {
        return $this->serviceId;
    }

    /**
     * @since v2.0.0
     */
    public function getPath()
    {
        return $this->path;
    }
}
