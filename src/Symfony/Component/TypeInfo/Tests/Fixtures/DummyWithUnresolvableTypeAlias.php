<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\Fixtures;

/**
 * @phpstan-type Flags = int-mask-of<1|2|4>
 * @phpstan-type FlagList = list<Flags>
 * @phpstan-type InvalidKey = array<mixed, string>
 * @phpstan-type Name = string
 */
final class DummyWithUnresolvableTypeAlias
{
}
