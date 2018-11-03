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

use PHPUnit\Framework\BaseTestListener;
use PHPUnit\Framework\Test;

/**
 * CoverageListener adds `@covers <className>` on each test when possible to
 * make the code coverage more accurate.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @internal
 */
class CoverageListenerForV6 extends BaseTestListener
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
