<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

class VoidNeverReturnTypeDummy
{
    public string $normalProperty = 'value';

    /**
     * @return string
     */
    public function getNormalProperty(): string
    {
        return $this->normalProperty;
    }

    public function getVoidProperty(): void
    {
        // This looks like a getter but returns void, should be ignored
    }

    public function getNeverProperty(): never
    {
        // This looks like a getter but returns never, should be ignored
        throw new \Exception('Never returns');
    }

    public function setValue(): void
    {
        // This looks like a setter but has no parameters, should be ignored as accessor
    }

    public function setNeverValue(): never
    {
        // This looks like a setter but has no parameters and returns never, should be ignored as accessor
        throw new \Exception('Never returns');
    }
}

