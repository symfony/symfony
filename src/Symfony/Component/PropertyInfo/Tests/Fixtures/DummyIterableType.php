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

class DummyIterableType
{
    /**
     * @var iterable<int, Dummy>
     */
    public $keyAndValue;

    /**
     * @var iterable<Dummy>
     */
    public $value;

    /**
     * @var iterable
     */
    public $plain;

    /**
     * @var iterable<int, Dummy>|null
     */
    public $nullable;

    /**
     * @var iterable<string, iterable<int, Dummy>>
     */
    public $nested;

    /**
     * @var iterable<array-key, Dummy>
     */
    public $arrayKey;
}
