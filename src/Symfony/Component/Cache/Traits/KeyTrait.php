<?php

namespace Symfony\Component\Cache\Traits;

trait KeyTrait
{
    /**
     * Encode a key if contain @ and / character and not already encoded with rawurlencode() function
     */
    public function encodeKey($key)
    {
        if (\is_string($key) && '' !== $key && false === strpos('%', $key) && false !== strpbrk($key, '@/')) {
            return rawurlencode($key);
        }
        return $key;
    }
}
