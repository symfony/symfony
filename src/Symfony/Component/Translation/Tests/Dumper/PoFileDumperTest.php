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

use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Dumper\PoFileDumper;
use Symfony\Component\Translation\Gettext;

class PoFileDumperTest extends \PHPUnit_Framework_TestCase
{
    public function testDump()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->add(array('foo' => 'bar'));

        $tempDir = sys_get_temp_dir();
        $dumper = new PoFileDumper();
        $dumperString = $dumper->dump($catalogue, array('path' => $tempDir));
        $this->assertEquals(file_get_contents(__DIR__.'/../fixtures/resources.po'), file_get_contents($tempDir.'/messages.en.po'), 'Resource has whitelines added.');

        unlink($tempDir.'/messages.en.po');
    }

    public function testHeader() {
        $header = Gettext::emptyHeader();
        $string = Gettext::headerToString($header);
        $tempDir = sys_get_temp_dir();
        $filename = $tempDir . DIRECTORY_SEPARATOR . 'header.en.po';
        file_put_contents($filename, $string);
        $this->assertEquals(file_get_contents(__DIR__.'/../fixtures/empty-header.po'), file_get_contents($filename));
        unlink($filename);
    }

    public function testDumpFullInterval()
    {
        /*
         * We need a way to dump a plural message into a Gettext
         * format with the structure according to
         * http://www.gnu.org/software/gettext/manual/gettext.html#Translating-plural-forms
         *
         * msgid "One sheep"
         * msgid_plural "%d sheep"
         * msgstr[0] "Un mouton"
         * msgstr[1] "@count sheep"
         *
         * but it is not yet posible to do as we cannot ask for ie an array
         * containing a processed version of '{0} un mouton|{1} @count moutons'
         *
         * MessageSelector::choose has the algoritme for interval and indexed
         * but Gettext PO (and MO?) does not understand interval.
         */
        $this->markTestSkipped('We need to find a way for interval messages plural handling');
        $catalogue = new MessageCatalogue('en');

        $header = Gettext::emptyHeader();

        $messages = array();
        Gettext::addHeader($messages, $header);
        $catalogue->add(array(Gettext::HEADER_KEY => $messages['__HEADER__']));
        $catalogue->add(array('One sheep' => 'un mouton'));
        // interval
        $catalogue->add(array('@count sheep' => '{0} un mouton|{1} @count moutons'));
        $catalogue->add(array('Monday' => 'lundi'));

        $tempDir = sys_get_temp_dir();
        $fileName = 'messages.en.po';
        $fullpath = $tempDir . DIRECTORY_SEPARATOR . $fileName;
        $dumper = new PoFileDumper();
        $dumperString = $dumper->dump($catalogue, array('path' => $tempDir));
        $this->assertEquals(file_get_contents(__DIR__.'/../fixtures/full.po'), file_get_contents($fullpath));
        unlink($fullpath);
    }

    public function testDumpFullIndexed()
    {
        $messages = array(
            'messages' => array(
                'One sheep' => 'un mouton',
                '@count sheep' => 'un mouton|@count moutons',
                'One sheep|@count sheep' => 'un mouton|@count moutons',
                'Monday' => 'lundi',
            ),
        );

        Gettext::addHeader($messages['messages'], Gettext::emptyHeader());

        $catalogue = new MessageCatalogue('en', $messages);

        $tempDir = sys_get_temp_dir();
        $fileName = 'messages.en.po';
        $fullpath = $tempDir . DIRECTORY_SEPARATOR . $fileName;
        $dumper = new PoFileDumper();
        $dumper->dump($catalogue, array('path' => $tempDir));
        $this->assertEquals(file_get_contents(__DIR__.'/../fixtures/full.po'), file_get_contents($fullpath));
        unlink($fullpath);
    }

}
