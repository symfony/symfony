<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\PhpUnit\Legacy;

/**
 * CoverageListener adds `@covers <className>` on each test suite when possible
 * to make the code coverage more accurate.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @internal
 */
class CoverageListenerForV5 extends \PHPUnit_Framework_BaseTestListener
{
    private $trait;

    public function __construct(callable $sutFqcnResolver = null, $warningOnSutNotFound = false)
    {
        $this->trait = new CoverageListenerTrait($sutFqcnResolver, $warningOnSutNotFound);
    }

    public function startTest(\PHPUnit_Framework_Test $test)
    {
        $this->trait->startTest($test);
    }
}
