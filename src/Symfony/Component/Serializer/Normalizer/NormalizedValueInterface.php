<?php

namespace Symfony\Component\Serializer\Normalizer;

interface NormalizedValueInterface {

    public function getNormalization(): array|string|int|float|bool|\ArrayObject|null;
    
}
