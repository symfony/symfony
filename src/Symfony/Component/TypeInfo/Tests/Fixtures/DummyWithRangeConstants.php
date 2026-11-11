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

final class DummyWithRangeConstants
{
    public const BROKEN = \UndefinedClass::VALUE;
    protected const MIN = 1;
    private const MAX = 10;
}
