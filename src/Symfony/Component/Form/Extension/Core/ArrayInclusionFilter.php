<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * @internal
 */
final class ArrayInclusionFilter
{
    private $acceptedKeys;

    public function __construct(array $acceptedKeys)
    {
        $this->acceptedKeys = array_fill_keys($acceptedKeys, true);
    }

    public function __invoke(string $k)
    {
        return isset($this->acceptedKeys[$k]);
    }
}
