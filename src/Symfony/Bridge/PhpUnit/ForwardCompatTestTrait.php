<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit;

use PHPUnit\Framework\TestCase;

// A trait to provide forward compatibility with newest PHPUnit versions

$r = new \ReflectionClass(TestCase::class);

if (\PHP_VERSION_ID < 70000 || !$r->hasMethod('createMock') || !$r->getMethod('createMock')->hasReturnType()) {
    trait ForwardCompatTestTrait
    {
        use Legacy\ForwardCompatTestTraitForV5;
    }
} elseif ($r->getMethod('tearDown')->hasReturnType()) {
    trait ForwardCompatTestTrait
    {
        use Legacy\ForwardCompatTestTraitForV8;
    }
} else {
    trait ForwardCompatTestTrait
    {
        use Legacy\ForwardCompatTestTraitForV7;
    }
}
