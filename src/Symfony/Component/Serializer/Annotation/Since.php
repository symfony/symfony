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
 * Annotation class for @Since().
 *
 * @Annotation
 * @Target({"PROPERTY", "METHOD"})
 *
 * @author Arnaud Tarroux <ta.arnaud@gmail.com>
 */
class Since
{
    /**
     * @var string
     */
    private $version;

    public function __construct(array $data)
    {
        if (!isset($data['value'])) {
            throw new InvalidArgumentException(sprintf('Parameter of annotation "%s" should be set.', \get_class($this)));
        }

        if (!\is_string($data['value']) || empty($data['value'])) {
            throw new InvalidArgumentException(sprintf('Parameter of annotation "%s" must be a non-empty string.', \get_class($this)));
        }

        $this->version = $data['value'];
    }

    public function getVersion(): string
    {
        return $this->version;
    }
}
