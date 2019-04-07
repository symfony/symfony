<?php

namespace Symfony\Component\Debug\Tests\Fixtures\DiscourageSerializable;

class ImplementsAnInterfaceThatExtendsSerializable implements ExtendsSerializable
{
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
