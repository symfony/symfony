<?php

namespace acme\lib;

class PhpDeprecation implements \Serializable
{
    public function serialize(): string
    {
        return serialize([]);
    }

    public function unserialize($data): void
    {
    }
}
