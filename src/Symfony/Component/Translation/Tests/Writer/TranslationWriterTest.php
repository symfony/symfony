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

use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Writer\TranslationWriter;

class TranslationWriterTest extends \PHPUnit_Framework_TestCase
{
    public function testWriteTranslations()
    {
        $dumper = $this->getMock('Symfony\Component\Translation\Dumper\DumperInterface');
        $dumper
            ->expects($this->once())
            ->method('dump');

        $writer = new TranslationWriter();
        $writer->addDumper('test', $dumper);
        $writer->writeTranslations(new MessageCatalogue(array()), 'test');
    }

    public function testDisableBackup()
    {
        $dumper = $this->getMock('Symfony\Component\Translation\Dumper\DumperInterface');
        $dumper
            ->expects($this->never())
            ->method('setBackup');
        $phpDumper = $this->getMock('Symfony\Component\Translation\Dumper\PhpFileDumper');
        $phpDumper
            ->expects($this->once())
            ->method('setBackup');

        $writer = new TranslationWriter();
        $writer->addDumper('test', $dumper);
        $writer->addDumper('php', $phpDumper);
        $writer->disableBackup();
    }
}
