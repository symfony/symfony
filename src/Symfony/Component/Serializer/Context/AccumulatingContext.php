<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Serializer\Context;

class AccumulatingContext extends \ArrayObject
{
    public function isEmpty(): bool
    {
        return 0 === $this->count();
    }

    public function flatten(): array
    {
        $flatContext = array();

        foreach ($this as $key => $value) {
            if (\is_array($value)) {
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
            if (\is_string($key) && \is_array($innerValue)) {
                $this->doFlatten($innerValue, $key.'.'.$innerKey, $flatContext);

                continue;
            }

            $flatContext[$key][] = $innerValue;
        }
    }
}
