<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\StaticSiteGeneration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\StaticSiteGeneration\FilesystemStaticPageDumper;

class FilesystemStaticPageDumperTest extends TestCase
{
    private string $staticPagesDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staticPagesDir = \sprintf('%s/symfony_http_kernel_test/static_pages/', sys_get_temp_dir());
        $this->filesystem = new Filesystem();

        if (is_dir($this->staticPagesDir)) {
            $this->filesystem->remove($this->staticPagesDir);
        }
    }

    public function testDumpContent()
    {
        $dumper = new FilesystemStaticPageDumper($this->staticPagesDir);
        $dumper->dump('page-foo', 'dummy-content');

        $expectedPath = \sprintf('%s/public/static-pages/%s', $this->staticPagesDir, 'page-foo');

        $this->assertTrue($this->filesystem->exists($expectedPath));
        $this->assertSame('dummy-content', $this->filesystem->readFile($expectedPath));
    }

    public function testAppendFormat()
    {
        $dumper = new FilesystemStaticPageDumper($this->staticPagesDir);
        $dumper->dump('page-foo', 'dummy-content', 'html');

        $expectedPath = \sprintf('%s/public/static-pages/%s.html', $this->staticPagesDir, 'page-foo');

        $this->assertTrue($this->filesystem->exists($expectedPath));
        $this->assertSame('dummy-content', $this->filesystem->readFile($expectedPath));
    }

    public function testCustomPublicDir()
    {
        $dumper = new FilesystemStaticPageDumper($this->staticPagesDir);
        $this->filesystem->dumpFile(\sprintf('%s/composer.json', $this->staticPagesDir), json_encode([
            'extra' => [
                'public-dir' => 'custom-public',
            ],
        ]));

        $dumper->dump('page-foo', 'dummy-content');
        $expectedPath = \sprintf('%s/custom-public/static-pages/%s', $this->staticPagesDir, 'page-foo');

        $this->assertTrue($this->filesystem->exists($expectedPath));
        $this->assertSame('dummy-content', $this->filesystem->readFile($expectedPath));
    }
}
