<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Instantiator;

use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;

/**
 * Contains the result of an instantiation process.
 *
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
final class InstantiatorResult
{
    private $object;
    private $data;
    private $context;
    private $error;

    public function __construct(?object $object, array $data, array $context, string $error = null)
    {
        $this->object = $object;
        $this->data = $data;
        $this->context = $context;
        $this->error = $error;
    }

    public function getObject(): ?object
    {
        return $this->object;
    }

    public function getUnusedData(): array
    {
        return $this->data;
    }

    public function getUnusedContext(): array
    {
        return $this->context;
    }

    public function getError(): ?\Exception
    {
        if (null === $this->error) {
            return null;
        }

        return new MissingConstructorArgumentsException($this->error);
    }

    public function hasFailed(): bool
    {
        return null === $this->object;
    }
}
