<?php

namespace Symfony\Component\Serializer\Annotation;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @Annotation
 * @Target({"PROPERTY"})
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class Methods
{
    /**
     * @var string
     */
    private $accessor;

    /**
     * @var string
     */
    private $mutator;

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        if (empty($data)) {
            throw new InvalidArgumentException(sprintf('Parameter of annotation "%s" cannot be empty.', get_class($this)));
        }

        foreach (array('getter', 'setter') as $parameter) {
            if (!isset($data[$parameter]) || !$data[$parameter]) {
                continue;
            }

            if (!is_string($data[$parameter])) {
                throw new InvalidArgumentException(sprintf('Parameter "%s" of annotation "%s" must be a string.', $parameter, get_class($this)));
            }

            $this->$parameter = $data[$parameter];
        }

        if (null === $this->accessor && null === $this->mutator) {
            throw new InvalidArgumentException(sprintf('Either option "getter" or "setter" must be given for annotation %s', get_class($this)));
        }
    }

    public function getAccessor(): ?string
    {
        return $this->accessor;
    }

    public function getMutator(): ?string
    {
        return $this->mutator;
    }
}
