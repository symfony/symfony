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
 * Annotation class for @SerializedName().
 *
 * @Annotation
 * @Target({"PROPERTY", "METHOD"})
 *
 * @author Fabien Bourigault <bourigaultfabien@gmail.com>
 */
final class SerializedName
{
    /**
     * @var string
     */
    private $serializedName;

    public function __construct(array $data)
    {
        if (!isset($data['value'])) {
            throw new InvalidArgumentException(sprintf('Parameter of annotation "%s" should be set.', \get_class($this)));
        }

        if (!\is_string($data['value']) || empty($data['value'])) {
            throw new InvalidArgumentException(sprintf('Parameter of annotation "%s" must be a non-empty string.', \get_class($this)));
        }

        $this->serializedName = $data['value'];
    }

    public function getSerializedName(): string
    {
        return $this->serializedName;
    }
}
