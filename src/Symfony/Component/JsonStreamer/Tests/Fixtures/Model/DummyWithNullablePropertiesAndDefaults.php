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

class DummyWithNullablePropertiesAndDefaults
{
    public ?string $name = 'default';
    public ?DummyBackedEnum $enum = DummyBackedEnum::ONE;
    public mixed $mixed = 'default';

    /** @var list<int>|null */
    public ?array $list = [1];
}
