<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\CacheWarmer;

use Symfony\Bundle\FrameworkBundle\CacheWarmer\TemplateFinderInterface;
use Symfony\Bundle\FrameworkBundle\CacheWarmer\TemplatePathsCacheWarmer;
use Symfony\Bundle\FrameworkBundle\Templating\Loader\TemplateLocator;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateReference;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @group legacy
 */
class TemplatePathsCacheWarmerTest extends TestCase
{
    /** @var Filesystem */
    private $filesystem;

    /** @var TemplateFinderInterface */
    private $templateFinder;

    /** @var FileLocator */
    private $fileLocator;

    /** @var TemplateLocator */
    private $templateLocator;

    private $tmpDir;

    protected function setUp()
    {
        $this->templateFinder = $this
            ->getMockBuilder(TemplateFinderInterface::class)
            ->setMethods(['findAllTemplates'])
            ->getMock();

        $this->fileLocator = $this
            ->getMockBuilder(FileLocator::class)
            ->setMethods(['locate'])
            ->setConstructorArgs(['/path/to/fallback'])
            ->getMock();

        $this->templateLocator = new TemplateLocator($this->fileLocator);

        $this->tmpDir = sys_get_temp_dir().\DIRECTORY_SEPARATOR.uniqid('cache_template_paths_', true);

        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir($this->tmpDir);
    }

    protected function tearDown()
    {
        $this->filesystem->remove($this->tmpDir);
    }

    public function testWarmUp()
    {
        $template = new TemplateReference('bundle', 'controller', 'name', 'format', 'engine');

        $this->templateFinder
            ->expects($this->once())
            ->method('findAllTemplates')
            ->willReturn([$template]);

        $this->fileLocator
            ->expects($this->once())
            ->method('locate')
            ->with($template->getPath())
            ->willReturn(\dirname($this->tmpDir).'/path/to/template.html.twig');

        $warmer = new TemplatePathsCacheWarmer($this->templateFinder, $this->templateLocator);
        $warmer->warmUp($this->tmpDir);

        $this->assertFileEquals(__DIR__.'/../Fixtures/TemplatePathsCache/templates.php', $this->tmpDir.'/templates.php');
    }

    public function testWarmUpEmpty()
    {
        $this->templateFinder
            ->expects($this->once())
            ->method('findAllTemplates')
            ->willReturn([]);

        $this->fileLocator
            ->expects($this->never())
            ->method('locate');

        $warmer = new TemplatePathsCacheWarmer($this->templateFinder, $this->templateLocator);
        $warmer->warmUp($this->tmpDir);

        $this->assertFileExists($this->tmpDir.'/templates.php');
        $this->assertSame(file_get_contents(__DIR__.'/../Fixtures/TemplatePathsCache/templates-empty.php'), file_get_contents($this->tmpDir.'/templates.php'));
    }
}
