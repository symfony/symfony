<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Traits;

use Symfony\Component\Cache\SerializerInterface;

/**
 * @author Alexei Prilipko <palex.fpt@gmail.com>
 *
 * @internal
 */
trait SerializerTrait
{
    /** @var SerializerInterface */
    private $serializer;

    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * Generates a storable representation of a value.
     *
     * @param $data mixed
     *
     * @return string|mixed serialized value
     */
    protected function serialize($data)
    {
        return $this->serializer->serialize($data);
    }

    /**
     * Creates a PHP value from a stored representation.
     *
     * @param string|mixed $serialized the serialized string
     *
     * @return mixed Original value
     */
    protected function unserialize($serialized)
    {
        return $this->serializer->unserialize($serialized);
    }

    protected function serializeMultiple(iterable $values)
    {
        foreach ($values as $key => $value) {
            yield $key => $this->serialize($value);
        }
    }

    protected function unserializeMultiple(iterable $serializedValues)
    {
        foreach ($serializedValues as $key => $value) {
            yield $key => $this->unserialize($value);
        }
    }
}
