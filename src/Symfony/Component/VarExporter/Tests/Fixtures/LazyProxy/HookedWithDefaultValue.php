<?php

namespace Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy;

class HookedWithDefaultValue
{
    public int $backedWithDefault = 321 {
        get => $this->backedWithDefault;
        set => $this->backedWithDefault = $value;
    }
}
