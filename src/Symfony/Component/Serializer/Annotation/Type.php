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
 * @Annotation
 * @Target({"PROPERTY"})
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class Type
{
    /**
     * @var string
     */
    private $type;

    public function __construct(array $data)
    {
        if (!isset($data['value']) || !$data['value']) {
            throw new InvalidArgumentException(sprintf('Parameter of annotation "%s" cannot be empty.', \get_class($this)));
        }

        if (!\is_string($data['value'])) {
            throw new InvalidArgumentException(sprintf('Parameter of annotation "%s" must be a string.', \get_class($this)));
        }

        $type = $data['value'];

        if (false !== $pos = strpos($type, '\\')) {
            // This is a referencet to a class
            if (0 !== $pos) {
                throw new InvalidArgumentException(sprintf('When referring to an class you you must begin the type with backslash (\\) you provided "%s" for annotation "%s".', $type, \get_class($this)));
            }
        }

        $this->type = $data['value'];
    }

    public function getType(): string
    {
        return $this->type;
    }
}
