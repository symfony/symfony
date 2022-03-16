<?php

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

class TypedPropertiesObjectWithGetters extends TypedPropertiesObject
{
    public function getUnInitialized(): string
    {
        return $this->unInitialized;
    }

    public function setUnInitialized(string $unInitialized): self
    {
        $this->unInitialized = $unInitialized;

        return $this;
    }

    public function getInitialized(): string
    {
        return $this->initialized;
    }

    public function setInitialized(string $initialized): self
    {
        $this->initialized = $initialized;

        return $this;
    }

    public function getInitialized2(): string
    {
        return $this->initialized2;
    }

    public function setInitialized2(string $initialized2): self
    {
        $this->initialized2 = $initialized2;

        return $this;
    }
}
