<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Annotation;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * Annotation class for @DiscriminatorMap().
 *
 * @Annotation
 * @Target({"CLASS"})
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class DiscriminatorMap
{
    /**
     * @var string
     */
    private $typeProperty;

    /**
     * @var array
     */
    private $mapping;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(array $data)
    {
        if (empty($data['typeProperty'])) {
            throw new InvalidArgumentException(sprintf('Parameter "typeProperty" of annotation "%s" cannot be empty.', static::class));
        }

        if (empty($data['mapping'])) {
            throw new InvalidArgumentException(sprintf('Parameter "mapping" of annotation "%s" cannot be empty.', static::class));
        }

        $this->typeProperty = $data['typeProperty'];
        $this->mapping = $data['mapping'];
    }

    public function getTypeProperty(): string
    {
        return $this->typeProperty;
    }

    public function getMapping(): array
    {
        return $this->mapping;
    }
}
