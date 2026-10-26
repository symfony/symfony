<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\Fixtures\Model;

use Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum;

class DummyWithNonNullableProperties
{
    public string $name = 'default';
    public int|string $union = 'default';
    public DummyBackedEnum $enum = DummyBackedEnum::ONE;
    public ClassicDummy $dummy;
    public \DateTimeImmutable $date;

    /** @var list<int> */
    public array $list = [1];
}
