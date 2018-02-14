<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Legacy;

use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use PHPUnit\Framework\TestSuite;

/**
 * CoverageListener adds `@covers <className>` on each test suite when possible
 * to make the code coverage more accurate.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @internal
 */
class CoverageListenerForV7 implements TestListener
{
    use TestListenerDefaultImplementation;

    private $trait;

    public function __construct(callable $sutFqcnResolver = null, $warningOnSutNotFound = false)
    {
        $this->trait = new CoverageListenerTrait($sutFqcnResolver, $warningOnSutNotFound);
    }

    public function startTestSuite(TestSuite $suite): void
    {
        $this->trait->startTest($test);
    }
}
