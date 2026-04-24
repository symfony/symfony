<?php

namespace Symfony\Component\Validator\Tests\Fixtures;
class EntityWithHook {

    protected int $id = 1;

    public string $name;
    protected string $withHook {
        get{
            $prop = new \ReflectionProperty(self::class, 'withHook');
            if (!$prop->isInitialized($this)) {
                $this->withHook = strtolower($this->name);
            }
            return $this->withHook;
        }
    }
}
