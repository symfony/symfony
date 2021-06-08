<?php

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

use Symfony\Component\Serializer\Annotation\Groups;

class TypedPropertiesObject
{
    /**
     * @Groups({"foo"})
     */
    public string $unInitialized;

    /**
     * @Groups({"foo"})
     */
    public string $initialized = 'value';

    /**
     * @Groups({"bar"})
     */
    public string $initialized2 = 'value';
}
