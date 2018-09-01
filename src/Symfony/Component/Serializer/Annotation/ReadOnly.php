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
 * @Target({"PROPERTY", "CLASS"})
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class ReadOnly
{
    /**
     * @var bool
     */
    private $readOnly;

    public function __construct(array $data = array())
    {
        if (empty($data) || !isset($data['value'])) {
            $this->readOnly = true;

            return;
        }

        if (!\is_bool($data['value'])) {
            throw new InvalidArgumentException(sprintf('Parameter of annotation "%s" must be a boolean.', \get_class($this)));
        }

        $this->readOnly = $data['value'];
    }

    public function getReadOnly(): bool
    {
        return $this->readOnly;
    }
}
