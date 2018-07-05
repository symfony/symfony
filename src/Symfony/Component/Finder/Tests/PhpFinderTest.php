<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Tests;

use Symfony\Component\Finder\Adapter\PhpAdapter;
use Symfony\Component\Finder\Finder;

/**
 * @group legacy
 */
class PhpFinderTest extends FinderTest
{
    public function testImplementationsAreSynchronized()
    {
        $adapterReflector = new \ReflectionMethod('Symfony\Component\Finder\Adapter\PhpAdapter', 'searchInDirectory');
        $finderReflector = new \ReflectionMethod('Symfony\Component\Finder\Finder', 'searchInDirectory');

        $adapterSource = \array_slice(file($adapterReflector->getFileName()), $adapterReflector->getStartLine() + 1, $adapterReflector->getEndLine() - $adapterReflector->getStartLine() - 1);
        $adapterSource = implode('', $adapterSource);
        $adapterSource = str_replace(array('$this->minDepth', '$this->maxDepth'), array('$minDepth', '$maxDepth'), $adapterSource);

        $finderSource = \array_slice(file($finderReflector->getFileName()), $finderReflector->getStartLine() + 1, $finderReflector->getEndLine() - $finderReflector->getStartLine() - 1);
        $finderSource = implode('', $finderSource);

        $this->assertStringEndsWith($adapterSource, $finderSource);
    }

    protected function buildFinder()
    {
        $adapter = new PhpAdapter();

        return Finder::create()
            ->removeAdapters()
            ->addAdapter($adapter);
    }
}
