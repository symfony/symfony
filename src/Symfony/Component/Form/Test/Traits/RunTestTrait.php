<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Test\Traits;

use PHPUnit\Framework\TestCase;

if ((new \ReflectionMethod(TestCase::class, 'runTest'))->hasReturnType()) {
    // PHPUnit 10
    /** @internal */
    trait RunTestTrait
    {
        protected function runTest(): mixed
        {
            return $this->doRunTest();
        }
    }
} else {
    // PHPUnit 9
    /** @internal */
    trait RunTestTrait
    {
        protected function runTest()
        {
            return $this->doRunTest();
        }
    }
}
