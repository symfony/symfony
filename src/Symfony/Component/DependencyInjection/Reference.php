<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

/**
 * Reference represents a service reference.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Reference
{
    public function __construct(
        private string $id,
        private int $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
    ) {
    }

    public function __toString(): string
    {
        return $this->id;
    }

    /**
     * Returns the behavior to be used when the service does not exist.
     */
    public function getInvalidBehavior(): int
    {
        return $this->invalidBehavior ??= ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
    }

    public function __serialize(): array
    {
        $data = [];
        foreach ((array) $this as $k => $v) {
            if (false !== $i = strrpos($k, "\0")) {
                $k = substr($k, 1 + $i);
            }
            if ('invalidBehavior' === $k && ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE === $v) {
                continue;
            }
            $data[$k] = $v;
        }

        return $data;
    }
}
