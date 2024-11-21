<?php

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

class Php84Dummy
{
    public private(set) bool $publicPrivateSet;
    public protected(set) bool $publicProtectedSet;
    public public(set) bool $publicPublicSet;
    protected private(set) bool $protectedPrivateSet;
    public bool $virtualNoSetHook { get => true; }
    public bool $virtualSetHookOnly { set => $value; }
    public bool $virtualHook { get => true; set => $value; }
}
