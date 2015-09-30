<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Filesystem\Tests;

use Symfony\Component\Filesystem\Filesystem;

class FilesystemTestCase extends \PHPUnit_Framework_TestCase
{
    private $umask;

    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $filesystem = null;

    /**
     * @var string
     */
    protected $workspace = null;

    private static $symlinkOnWindows = null;

    public static function setUpBeforeClass()
    {
        if ('\\' === DIRECTORY_SEPARATOR && null === self::$symlinkOnWindows) {
            $target = tempnam(sys_get_temp_dir(), 'sl');
            $link = sys_get_temp_dir().'/sl'.microtime(true).mt_rand();
            if (self::$symlinkOnWindows = @symlink($target, $link)) {
                unlink($link);
            }
            unlink($target);
        }
    }

    protected function setUp()
    {
        $this->umask = umask(0);
        $this->workspace = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.time().mt_rand(0, 1000);
        mkdir($this->workspace, 0777, true);
        $this->workspace = realpath($this->workspace);
        $this->filesystem = new Filesystem();
    }

    protected function tearDown()
    {
        $this->filesystem->remove($this->workspace);
        umask($this->umask);
    }

    /**
     * @param int    $expectedFilePerms expected file permissions as three digits (i.e. 755)
     * @param string $filePath
     */
    protected function assertFilePermissions($expectedFilePerms, $filePath)
    {
        $actualFilePerms = (int) substr(sprintf('%o', fileperms($filePath)), -3);
        $this->assertEquals(
            $expectedFilePerms,
            $actualFilePerms,
            sprintf('File permissions for %s must be %s. Actual %s', $filePath, $expectedFilePerms, $actualFilePerms)
        );
    }

    protected function getFileOwner($filepath)
    {
        $this->markAsSkippedIfPosixIsMissing();

        $infos = stat($filepath);
        if ($datas = posix_getpwuid($infos['uid'])) {
            return $datas['name'];
        }
    }

    protected function getFileGroup($filepath)
    {
        $this->markAsSkippedIfPosixIsMissing();

        $infos = stat($filepath);
        if ($datas = posix_getgrgid($infos['gid'])) {
            return $datas['name'];
        }

        $this->markTestSkipped('Unable to retrieve file group name');
    }

    protected function markAsSkippedIfSymlinkIsMissing()
    {
        if (!function_exists('symlink')) {
            $this->markTestSkipped('symlink is not supported');
        }

        if ('\\' === DIRECTORY_SEPARATOR && false === self::$symlinkOnWindows) {
            $this->markTestSkipped('symlink requires "Create symbolic links" privilege on windows');
        }
    }

    protected function markAsSkippedIfChmodIsMissing()
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('chmod is not supported on windows');
        }
    }

    protected function markAsSkippedIfPosixIsMissing()
    {
        if ('\\' === DIRECTORY_SEPARATOR || !function_exists('posix_isatty')) {
            $this->markTestSkipped('Posix is not supported');
        }
    }
}
