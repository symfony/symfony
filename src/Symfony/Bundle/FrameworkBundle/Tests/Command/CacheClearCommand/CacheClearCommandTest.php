<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Command\CacheClearCommand;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Tests\Command\CacheClearCommand\Fixture\TestAppKernel;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Filesystem\Filesystem;

class CacheClearCommandTest extends TestCase
{
    /** @var TestAppKernel */
    private $kernel;
    /** @var Filesystem */
    private $fs;
    private $rootDir;

    protected function setUp()
    {
        $this->fs = new Filesystem();
        $this->kernel = new TestAppKernel('test', true);
        $this->rootDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('sf2_cache_', true);
        $this->kernel->setRootDir($this->rootDir);
        $this->fs->mkdir($this->rootDir);
    }

    protected function tearDown()
    {
        $this->fs->remove($this->rootDir);
    }

    public function testCacheIsCleared()
    {
        $input = new ArrayInput(array('cache:clear'));
        $application = new Application($this->kernel);
        $application->setCatchExceptions(false);

        $application->doRun($input, new NullOutput());

        $this->assertDirectoryNotExists($this->kernel->getCacheDir());
    }
}
