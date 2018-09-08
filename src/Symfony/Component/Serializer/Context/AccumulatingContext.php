<?php

declare(strict_types=1);

namespace Symfony\Component\Serializer\Context;

class AccumulatingContext extends \ArrayObject
{
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function flatten(): array
    {
        $flatContext = [];

        foreach ($this as $key => $value) {
            if (is_array($value)) {
                $this->doFlatten($value, $key, $flatContext);

                continue;
            }

            $flatContext[$key] = $value;
        }

        return $flatContext;
    }

    private function doFlatten(array $value, $key, array &$flatContext)
    {
        foreach ($value as $innerKey => $innerValue) {
            if (is_string($key) && is_array($innerValue)) {
                $this->doFlatten($innerValue, $key . '.' . $innerKey, $flatContext);

                continue;
            }

            $flatContext[$key][] = $innerValue;
        }
    }
}
