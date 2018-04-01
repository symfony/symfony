<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Translation\Tests\Writer;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Translation\Dumper\DumperInterface;
use Symphony\Component\Translation\MessageCatalogue;
use Symphony\Component\Translation\Writer\TranslationWriter;

class TranslationWriterTest extends TestCase
{
    public function testWrite()
    {
        $dumper = $this->getMockBuilder('Symphony\Component\Translation\Dumper\DumperInterface')->getMock();
        $dumper
            ->expects($this->once())
            ->method('dump');

        $writer = new TranslationWriter();
        $writer->addDumper('test', $dumper);
        $writer->write(new MessageCatalogue('en'), 'test');
    }

    /**
     * @group legacy
     */
    public function testDisableBackup()
    {
        $nonBackupDumper = new NonBackupDumper();
        $backupDumper = new BackupDumper();

        $writer = new TranslationWriter();
        $writer->addDumper('non_backup', $nonBackupDumper);
        $writer->addDumper('backup', $backupDumper);
        $writer->disableBackup();

        $this->assertFalse($backupDumper->backup, 'backup can be disabled if setBackup() method does exist');
    }
}

class NonBackupDumper implements DumperInterface
{
    public function dump(MessageCatalogue $messages, $options = array())
    {
    }
}

class BackupDumper implements DumperInterface
{
    public $backup = true;

    public function dump(MessageCatalogue $messages, $options = array())
    {
    }

    public function setBackup($backup)
    {
        $this->backup = $backup;
    }
}
