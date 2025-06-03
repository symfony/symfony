<?php

namespace Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy;

class HookedWithDefaultValue
{
    public int $backedIntWithDefault = 321 {
        get => $this->backedIntWithDefault;
        set => $this->backedIntWithDefault = $value;
    }

    public string $backedStringWithDefault = '321' {
        get => $this->backedStringWithDefault;
        set => $this->backedStringWithDefault = $value;
    }

    public bool $backedBoolWithDefault = false {
        get => $this->backedBoolWithDefault;
        set => $this->backedBoolWithDefault = $value;
    }
}
