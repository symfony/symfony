<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests;

use PHPUnit\Framework\TestCase;

class SimplePhpunitTest extends TestCase
{
    private $tmpDir;

    protected function tearDown(): void
    {
        if ($this->tmpDir) {
            exec('rm -rf '.escapeshellarg($this->tmpDir));
        }
    }

    public function testInstallsInTheProjectVendorWhenVendorIsASymlink()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test cannot be run on Windows.');
        }

        $this->tmpDir = realpath(sys_get_temp_dir()).'/sf_simple_phpunit_'.bin2hex(random_bytes(6));
        $project = $this->tmpDir.'/project';
        $vendor = $this->tmpDir.'/vendor';

        mkdir($project, 0777, true);
        mkdir($vendor.'/bin', 0777, true);
        mkdir($vendor.'/symfony/phpunit-bridge/bin', 0777, true);
        file_put_contents($project.'/composer.json', '{}');

        foreach (['composer.json', 'DeprecationErrorHandler.php', 'bin/simple-phpunit', 'bin/simple-phpunit.php'] as $file) {
            copy(\dirname(__DIR__).'/'.$file, $vendor.'/symfony/phpunit-bridge/'.$file);
        }

        symlink('../symfony/phpunit-bridge/bin/simple-phpunit', $vendor.'/bin/simple-phpunit');
        symlink($vendor, $project.'/vendor');

        // stops the run at the first composer call and reports where PHPUnit would be installed
        $composer = $this->tmpDir.'/composer';
        file_put_contents($composer, "#!/usr/bin/env php\n<?php\necho 'INSTALLING IN ', getcwd(), \"\\n\";\nexit(1);\n");

        $php = escapeshellarg(\PHP_BINARY).('phpdbg' === \PHP_SAPI ? ' -qrr' : '');
        $cmd = $php.' vendor/bin/simple-phpunit 2>&1';
        $env = ['PATH' => getenv('PATH'), 'COMPOSER_BINARY' => $composer];
        $process = proc_open($cmd, [1 => ['pipe', 'w']], $pipes, $project, $env);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($process);

        $this->assertStringContainsString('INSTALLING IN '.$vendor.'/bin/.phpunit', $output);
    }
}
