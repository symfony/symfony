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

use Symfony\Component\Translation\Dumper\NullFileDumper;
use Symfony\Component\Translation\MessageCatalogue;

class NullFileDumperTest extends \PHPUnit_Framework_TestCase
{
    public function testDumpBackupsFileIfExisting()
    {
        $tempDir = sys_get_temp_dir();
        $file = $tempDir.'/messages.en.null';
        $backupFile = $file.'~';

        @touch($file);

        $catalogue = new MessageCatalogue('en');
        $catalogue->add(array('foo' => 'bar'));

        $dumper = new NullFileDumper();
        $dumper->dump($catalogue, array('path' => $tempDir));

        $this->assertTrue(file_exists($backupFile));

        @unlink($file);
        @unlink($backupFile);
    }

    public function testDumpCreatesNestedDirectoriesAndFile()
    {
        $tempDir = sys_get_temp_dir();
        $translationsDir = $tempDir.'/test/translations';
        $file = $translationsDir.'/messages.en.null';

        $catalogue = new MessageCatalogue('en');
        $catalogue->add(array('foo' => 'bar'));

        $dumper = new NullFileDumper();
        $dumper->setRelativePathTemplate('test/translations/{domain}.{locale}.{extension}');
        $dumper->dump($catalogue, array('path' => $tempDir));

        $this->assertTrue(file_exists($file));

        @unlink($file);
        @rmdir($translationsDir);
    }
}
