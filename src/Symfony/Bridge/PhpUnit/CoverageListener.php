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

use PHPUnit\Framework\BaseTestListener;
use PHPUnit\Framework\Test;
use Symfony\Bridge\PhpUnit\Legacy\CoverageListenerTrait;

if (class_exists('PHPUnit_Runner_Version') && version_compare(\PHPUnit_Runner_Version::id(), '6.0.0', '<')) {
    class_alias('Symfony\Bridge\PhpUnit\Legacy\CoverageListener', 'Symfony\Bridge\PhpUnit\CoverageListener');
// Using an early return instead of a else does not work when using the PHPUnit
// phar due to some weird PHP behavior (the class gets defined without executing
// the code before it and so the definition is not properly conditional)
} else {
    /**
     * CoverageListener adds `@covers <className>` on each test suite when possible
     * to make the code coverage more accurate.
     *
     * @author Grégoire Pineau <lyrixx@lyrixx.info>
     */
    class CoverageListener extends BaseTestListener
    {
        private $trait;

        public function __construct(callable $sutFqcnResolver = null, $warningOnSutNotFound = false)
        {
            $this->trait = new CoverageListenerTrait($sutFqcnResolver, $warningOnSutNotFound);
        }

        public function startTest(Test $test)
        {
            $this->trait->startTest($test);
        }
    }
}
