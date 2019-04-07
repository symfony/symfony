<?php

namespace Symfony\Component\Debug\Tests\Fixtures\DiscourageSerializable;

class ImplementsSerializable implements \Serializable
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

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
    }
}
