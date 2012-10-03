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

use Symfony\Component\Finder\FinderFactory;

/**
 * Test Finder Factory
 *
 * @author Beau Simensen <beau@dflydev.com>
 */
class FinderFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test createFinder() method
     */
    public function testCreateFinder()
    {
        $finderFactory = new FinderFactory;

        $this->assertInstanceOf('Symfony\Component\Finder\Finder', $finderFactory->createFinder());
    }
}
