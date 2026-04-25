<?php

namespace Symfony\Component\Validator\Tests\Fixtures;

class Entity_84_PropertyHook
{
    public int $uninitialized {
        get {
            if (!(new \ReflectionProperty(self::class, 'uninitialized'))->isInitialized($this)) {
                $this->uninitialized = 42;
            }

            return $this->uninitialized;
        }
    }
}
