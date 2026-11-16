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

    public function testMaxParallelLimitsHowManyComponentsRunAtOnce()
    {
        $this->tmpDir = realpath(sys_get_temp_dir()).'/sf_simple_phpunit_'.bin2hex(random_bytes(6));
        $phpunitDir = $this->tmpDir.'/.phpunit';
        $project = $this->tmpDir.'/project';

        mkdir($phpunitDir.'/phpunit-9.6-0', 0777, true);
        mkdir($project.'/running', 0777, true);
        foreach (['A', 'B', 'C', 'Fail'] as $component) {
            mkdir($project.'/src/'.$component, 0777, true);
            touch($project.'/src/'.$component.'/phpunit.xml.dist');
        }

        // a fake phpunit that logs how many components are running when it starts
        file_put_contents($phpunitDir.'/phpunit-9.6-0/phpunit', '<?php $c = basename(end($argv)); touch("running/$c"); file_put_contents("counts", count(glob("running/*"))."\n", FILE_APPEND | LOCK_EX); usleep(500000); unlink("running/$c"); exit("Fail" === $c ? 1 : 0);');

        $bin = \dirname(__DIR__).'/bin/simple-phpunit';
        file_put_contents($phpunitDir.'/.phpunit-9.6-0.md5', md5(implode(\PHP_EOL, [md5_file($bin.'.php'), 'phpspec/prophecy', '', 0])));
        file_put_contents($composer = $this->tmpDir.'/composer', "#!/usr/bin/env php\n<?php\nexit(1);\n");

        $php = escapeshellarg(\PHP_BINARY).('phpdbg' === \PHP_SAPI ? ' -qrr' : '');
        $env = ['PATH' => getenv('PATH'), 'COMPOSER_BINARY' => $composer, 'SYMFONY_PHPUNIT_DIR' => $phpunitDir, 'SYMFONY_PHPUNIT_VERSION' => '9.6', 'SYMFONY_PHPUNIT_MAX_PARALLEL' => '2'];
        $process = proc_open($php.' '.escapeshellarg($bin).' src 2>&1', [1 => ['pipe', 'w']], $pipes, $project, $env);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $this->assertSame(1, proc_close($process), $output);
        $this->assertStringContainsString("KO\033[0m src/Fail", $output);

        $counts = array_map('intval', file($project.'/counts'));
        $this->assertCount(4, $counts);
        $this->assertLessThanOrEqual(2, max($counts));
    }
}
