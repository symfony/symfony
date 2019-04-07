<?php

namespace Symfony\Component\Debug\Tests\Fixtures\DiscourageSerializable;

interface ExtendsSerializableWithTheNewMechanism extends \Serializable
{
    public function __serialize(): array;

    public function __unserialize(array $data): void;
}
