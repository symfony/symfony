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
 */
class ServiceCircularReferenceException extends RuntimeException
{
    public function __construct(
        private string $serviceId,
        private array $path,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(\sprintf('Circular reference detected for service "%s", path: "%s".', $serviceId, implode(' -> ', $path)), 0, $previous);
    }

    public function getServiceId(): string
    {
        return $this->serviceId;
    }

    public function getPath(): array
    {
        return $this->path;
    }
}
