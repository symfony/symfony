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
use Symfony\Component\Translation\Dumper\XliffFileDumper;

class XliffFileDumperTest extends \PHPUnit_Framework_TestCase
{
    public function testDump()
    {
        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add(array(
            'foo' => 'bar',
            'key' => '',
            'key.with.cdata' => '<source> & <target>',
        ));
        $catalogue->setMetadata('foo', array('notes' => array(array('priority' => 1, 'from' => 'bar', 'content' => 'baz'))));
        $catalogue->setMetadata('key', array('notes' => array(array('content' => 'baz'), array('content' => 'qux'))));

        $tempDir = sys_get_temp_dir();
        $dumper = new XliffFileDumper();
        $dumper->dump($catalogue, array('path' => $tempDir, 'default_locale' => 'fr_FR'));

        $this->assertEquals(
            file_get_contents(__DIR__.'/../fixtures/resources-clean.xlf'),
            file_get_contents($tempDir.'/messages.en_US.xlf')
        );

        unlink($tempDir.'/messages.en_US.xlf');
    }

    public function testExtendedDumperCanSetCustomToolInfo()
    {
        $dumper = new XliffExtendedFileDumper();

        $this->assertContains(
            '<tool tool-id="symfony" tool-name="Test" tool-version="0.0"/>',
            $dumper->formatTestMessages( array() )
        );
    }

}

/**
 * Test subclass for configuring dumper via protected functions.
 */
class XliffExtendedFileDumper extends XliffFileDumper {
    public function formatTestMessages( array $messages ){

        $this->setToolMetadata(array(
            'tool-name' => 'Test',
            'tool-version' => '0.0',
        ));

        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add( $messages, 'foo' );

        return $this->format( $catalogue, 'foo' );
    }
}