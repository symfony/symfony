<?php

namespace Symfony\Component\VarDumper\Tests\Fixtures;

class ExtendsReflectionTypeFixture extends \ReflectionType
{
    public function allowsNull(): bool
    {
        return false;
    }

    public function isBuiltin(): bool
    {
        return false;
    }

    public function __toString(): string
    {
        return 'fake';
    }
}
