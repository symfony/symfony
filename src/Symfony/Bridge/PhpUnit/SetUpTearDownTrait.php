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

// Auto-adapt to PHPUnit 8 that added a `void` return-type to the setUp/tearDown methods

if (method_exists(\ReflectionMethod::class, 'hasReturnType') && (new \ReflectionMethod(TestCase::class, 'tearDown'))->hasReturnType()) {
    eval('
    namespace Symfony\Bridge\PhpUnit;

    trait SetUpTearDownTrait
    {
        private function doSetUp(): void
        {
            parent::setUp();
        }

        private function doTearDown(): void
        {
            parent::tearDown();
        }

        protected function setUp(): void
        {
            $this->doSetUp();
        }

        protected function tearDown(): void
        {
            $this->doTearDown();
        }
    }
');
} else {
    trait SetUpTearDownTrait
    {
        /**
         * @return void
         */
        private function doSetUp()
        {
            parent::setUp();
        }

        /**
         * @return void
         */
        private function doTearDown()
        {
            parent::tearDown();
        }

        /**
         * @return void
         */
        protected function setUp()
        {
            $this->doSetUp();
        }

        /**
         * @return void
         */
        protected function tearDown()
        {
            $this->doTearDown();
        }
    }
}
