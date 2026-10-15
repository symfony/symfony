<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Dumper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Dumper\FileDumper;
use Symfony\Component\Translation\MessageCatalogue;

class FileDumperTest extends TestCase
{
    public function testDump()
    {
        $tempDir = sys_get_temp_dir();

        $catalogue = new MessageCatalogue('en');
        $catalogue->add(['foo' => 'bar']);

        $dumper = new ConcreteFileDumper();
        $dumper->dump($catalogue, ['path' => $tempDir]);

        $this->assertFileExists($tempDir.'/messages.en.concrete');

        @unlink($tempDir.'/messages.en.concrete');
    }

    public function testDumpIntl()
    {
        $tempDir = sys_get_temp_dir();

        $catalogue = new MessageCatalogue('en');
        $catalogue->add(['foo' => 'bar'], 'd1');
        $catalogue->add(['bar' => 'foo'], 'd1+intl-icu');
        $catalogue->add(['bar' => 'foo'], 'd2+intl-icu');

        $dumper = new ConcreteFileDumper();
        @unlink($tempDir.'/d2.en.concrete');
        $dumper->dump($catalogue, ['path' => $tempDir]);

        $this->assertStringEqualsFile($tempDir.'/d1.en.concrete', 'foo=bar');
        @unlink($tempDir.'/d1.en.concrete');

        $this->assertStringEqualsFile($tempDir.'/d1+intl-icu.en.concrete', 'bar=foo');
        @unlink($tempDir.'/d1+intl-icu.en.concrete');

        $this->assertFileDoesNotExist($tempDir.'/d2.en.concrete');
        $this->assertStringEqualsFile($tempDir.'/d2+intl-icu.en.concrete', 'bar=foo');
        @unlink($tempDir.'/d2+intl-icu.en.concrete');
    }

    public function testDumpCreatesNestedDirectoriesAndFile()
    {
        $tempDir = sys_get_temp_dir();
        $translationsDir = $tempDir.'/test/translations';
        $file = $translationsDir.'/messages.en.concrete';

        $catalogue = new MessageCatalogue('en');
        $catalogue->add(['foo' => 'bar']);

        $dumper = new ConcreteFileDumper();
        $dumper->setRelativePathTemplate('test/translations/%domain%.%locale%.%extension%');
        $dumper->dump($catalogue, ['path' => $tempDir]);

        $this->assertFileExists($file);

        @unlink($file);
        @rmdir($translationsDir);
    }

    public function testDumpWhenTheDirectoryIsCreatedConcurrently()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Creating symlinks requires extra privileges on Windows.');
        }

        $tempDir = sys_get_temp_dir().'/sf_file_dumper_'.uniqid();
        mkdir($tempDir);
        symlink($tempDir.'/target', $tempDir.'/link');

        $catalogue = new MessageCatalogue('en');
        $catalogue->add(['foo' => 'bar']);

        $dumper = new ConcreteFileDumper();
        $dumper->setRelativePathTemplate('link/%domain%.%locale%.%extension%');

        // mkdir() fails with "File exists" on the dangling link, and the error handler then creates its target, as a concurrent process would
        set_error_handler(static fn () => mkdir($tempDir.'/target'));

        try {
            $dumper->dump($catalogue, ['path' => $tempDir]);
        } finally {
            restore_error_handler();
        }

        $this->assertStringEqualsFile($tempDir.'/target/messages.en.concrete', 'foo=bar');

        @unlink($tempDir.'/target/messages.en.concrete');
        @rmdir($tempDir.'/target');
        @unlink($tempDir.'/link');
        @rmdir($tempDir);
    }
}

class ConcreteFileDumper extends FileDumper
{
    public function formatCatalogue(MessageCatalogue $messages, $domain, array $options = []): string
    {
        return http_build_query($messages->all($domain), '', '&');
    }

    protected function getExtension(): string
    {
        return 'concrete';
    }
}
