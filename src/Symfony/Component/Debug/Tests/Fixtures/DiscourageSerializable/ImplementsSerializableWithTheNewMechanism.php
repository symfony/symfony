<?php

namespace Symfony\Component\Debug\Tests\Fixtures\DiscourageSerializable;

class ImplementsSerializableWithTheNewMechanism implements \Serializable
{
    public function __serialize(): array
    {
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return 'foo';
    }

    public function __unserialize(array $data): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
    }
}
