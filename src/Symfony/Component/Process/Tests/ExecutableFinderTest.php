<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process\Tests;

use Symfony\Component\Process\ExecutableFinder;

/**
 * @author Chris Smith <chris@cs278.org>
 */
class ExecutableFinderTest extends \PHPUnit_Framework_TestCase
{
    public function testFindWithOpenBaseDir()
    {
        if (!defined('PHP_BINARY')) {
            $this->markTestSkipped('Requires the PHP_BINARY constant');
        }

        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('Cannot run test on windows');
        }

        if (ini_get('open_basedir')) {
            $this->markTestSkipped('Cannot test when open_basedir is set');
        }

        ini_set('open_basedir', dirname(PHP_BINARY).PATH_SEPARATOR.'/');

        $finder = new ExecutableFinder;
        $result = $finder->find(basename(PHP_BINARY));

        $this->assertEquals(PHP_BINARY, $result);
    }
}
