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
 * @Target({"CLASS"})
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class ExclusionPolicy
{
    const NONE = 'NONE';
    const ALL = 'ALL';

    /**
     * @var string
     */
    private $policy;

    public function __construct(array $data)
    {
        if (!isset($data['value']) || !$data['value']) {
            throw new InvalidArgumentException(sprintf('Parameter of annotation "%s" cannot be empty.', \get_class($this)));
        }

        if (!\is_string($data['value'])) {
            throw new InvalidArgumentException(sprintf('Parameter of annotation "%s" must be a string.', \get_class($this)));
        }

        $this->policy = strtoupper($data['value']);

        if (self::NONE !== $this->policy && self::ALL !== $this->policy) {
            throw new InvalidArgumentException('Exclusion policy must either be "ALL", or "NONE".');
        }
    }

    public function getPolicy(): string
    {
        return $this->policy;
    }
}
