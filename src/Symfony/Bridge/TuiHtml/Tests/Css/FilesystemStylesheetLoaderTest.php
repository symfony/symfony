<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Css;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Css\FilesystemStylesheetLoader;
use Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException;

final class FilesystemStylesheetLoaderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/tui-html-loader-'.bin2hex(random_bytes(4));
        mkdir($this->directory.'/public/css', 0o777, true);
        file_put_contents($this->directory.'/public/css/app.css', 'p { color: red }');
        file_put_contents($this->directory.'/secret.css', 'p { color: blue }');
    }

    protected function tearDown(): void
    {
        unlink($this->directory.'/public/css/app.css');
        unlink($this->directory.'/secret.css');
        rmdir($this->directory.'/public/css');
        rmdir($this->directory.'/public');
        rmdir($this->directory);
    }

    public function testLoadsFromTheDirectories()
    {
        $loader = new FilesystemStylesheetLoader([$this->directory.'/nowhere', $this->directory.'/public']);
        $this->assertSame('p { color: red }', $loader->load('css/app.css'));
        $this->assertSame('p { color: red }', $loader->load('/css/app.css'));
        $this->assertSame('p { color: red }', $loader->load('css/app.css?v=2'));
        $this->assertSame('p { color: red }', $loader->load('file:///css/app.css'), 'A path in the directories, whatever the scheme.');
    }

    #[DataProvider('provideParentReferences')]
    public function testCannotReachAParentDirectory(string $href)
    {
        $loader = new FilesystemStylesheetLoader([$this->directory.'/public/css']);

        try {
            $loader->load($href);
            $this->fail('Expected an exception.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringNotContainsString($this->directory, $e->getMessage());
        }
    }

    public static function provideParentReferences(): iterable
    {
        yield 'parent' => ['../../secret.css'];
        yield 'parent in the middle' => ['x/../../../secret.css'];
        yield 'url' => ['file:///../../secret.css'];
        yield 'windows separators' => ['..\\..\\secret.css'];
        yield 'mixed separators' => ['x/..\\..\\..\\secret.css'];
        yield 'null byte' => ["app.css\0.png"];
        yield 'absolute path' => [\dirname(__DIR__, 2).'/composer.json'];
    }

    public function testTheMessageDoesNotTellTheDirectories()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The stylesheet "missing.css" was not found.');
        (new FilesystemStylesheetLoader([$this->directory.'/public']))->load('missing.css');
    }
}
