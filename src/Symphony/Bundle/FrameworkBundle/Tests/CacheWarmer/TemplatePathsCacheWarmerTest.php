<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\CacheWarmer;

use Symphony\Bundle\FrameworkBundle\CacheWarmer\TemplateFinderInterface;
use Symphony\Bundle\FrameworkBundle\CacheWarmer\TemplatePathsCacheWarmer;
use Symphony\Bundle\FrameworkBundle\Templating\Loader\TemplateLocator;
use Symphony\Bundle\FrameworkBundle\Templating\TemplateReference;
use Symphony\Bundle\FrameworkBundle\Tests\TestCase;
use Symphony\Component\Config\FileLocator;
use Symphony\Component\Filesystem\Filesystem;

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
            ->setMethods(array('findAllTemplates'))
            ->getMock();

        $this->fileLocator = $this
            ->getMockBuilder(FileLocator::class)
            ->setMethods(array('locate'))
            ->setConstructorArgs(array('/path/to/fallback'))
            ->getMock();

        $this->templateLocator = new TemplateLocator($this->fileLocator);

        $this->tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('cache_template_paths_', true);

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
            ->will($this->returnValue(array($template)));

        $this->fileLocator
            ->expects($this->once())
            ->method('locate')
            ->with($template->getPath())
            ->will($this->returnValue(dirname($this->tmpDir).'/path/to/template.html.twig'));

        $warmer = new TemplatePathsCacheWarmer($this->templateFinder, $this->templateLocator);
        $warmer->warmUp($this->tmpDir);

        $this->assertFileEquals(__DIR__.'/../Fixtures/TemplatePathsCache/templates.php', $this->tmpDir.'/templates.php');
    }

    public function testWarmUpEmpty()
    {
        $this->templateFinder
            ->expects($this->once())
            ->method('findAllTemplates')
            ->will($this->returnValue(array()));

        $this->fileLocator
            ->expects($this->never())
            ->method('locate');

        $warmer = new TemplatePathsCacheWarmer($this->templateFinder, $this->templateLocator);
        $warmer->warmUp($this->tmpDir);

        $this->assertFileExists($this->tmpDir.'/templates.php');
        $this->assertSame(file_get_contents(__DIR__.'/../Fixtures/TemplatePathsCache/templates-empty.php'), file_get_contents($this->tmpDir.'/templates.php'));
    }
}
