<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Writer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Dumper\DumperInterface;
use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Component\Translation\Exception\RuntimeException;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Writer\TranslationWriter;

class TranslationWriterTest extends TestCase
{
    public function testWrite()
    {
        $dumper = $this->createMock(DumperInterface::class);
        $dumper
            ->expects($this->once())
            ->method('dump');

        $writer = new TranslationWriter();
        $writer->addDumper('test', $dumper);
        $writer->write(new MessageCatalogue('en'), 'test');
    }

    public function testGetFormats()
    {
        $writer = new TranslationWriter();
        $writer->addDumper('foo', $this->createMock(DumperInterface::class));
        $writer->addDumper('bar', $this->createMock(DumperInterface::class));

        $this->assertEquals(['foo', 'bar'], $writer->getFormats());
    }

    public function testFormatIsNotSupported()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('There is no dumper associated with format "foo".');
        $writer = new TranslationWriter();

        $writer->write(new MessageCatalogue('en'), 'foo');
    }

    public function testUnwritableDirectory()
    {
        $writer = new TranslationWriter();
        $writer->addDumper('foo', $this->createMock(DumperInterface::class));

        $path = tempnam(sys_get_temp_dir(), '');
        file_put_contents($path, '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('Translation Writer was not able to create directory "%s".', $path));

        $writer->write(new MessageCatalogue('en'), 'foo', ['path' => $path]);
    }
}
