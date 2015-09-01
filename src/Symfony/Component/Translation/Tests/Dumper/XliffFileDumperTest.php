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
    private $tempDir;

    protected function setUp()
    {
        $this->tempDir = sys_get_temp_dir();
    }

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

        $dumper = new XliffFileDumper();
        $dumper->dump($catalogue, array('path' => $this->tempDir, 'default_locale' => 'fr_FR'));

        $this->assertEquals(
            file_get_contents(__DIR__.'/../fixtures/resources-clean.xlf'),
            file_get_contents($this->tempDir.'/messages.en_US.xlf')
        );

        unlink($this->tempDir.'/messages.en_US.xlf');
    }

    public function testDumpWithCustomToolInfo()
    {
        $options = array(
            'path' => $this->tempDir,
            'default_locale' => 'en_US',
            'tool_info' => array('tool-id' => 'foo', 'tool-name' => 'foo', 'tool-version' => '0.0', 'tool-company' => 'Foo'),
        );

        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add(array('foo' => 'bar'));

        $dumper = new XliffFileDumper();
        $dumper->dump($catalogue, $options);

        $this->assertEquals(
            file_get_contents(__DIR__.'/../fixtures/resources-tool-info.xlf'),
            file_get_contents($this->tempDir.'/messages.en_US.xlf')
        );

        unlink($this->tempDir.'/messages.en_US.xlf');
    }

    public function testDumpWithTargetAttributesMetadata()
    {
        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add(array(
            'foo' => 'bar',
        ));
        $catalogue->setMetadata('foo', array('target-attributes' => array('state' => 'needs-translation')));

        $this->tempDir = sys_get_temp_dir();
        $dumper = new XliffFileDumper();
        $dumper->dump($catalogue, array('path' => $this->tempDir, 'default_locale' => 'fr_FR'));

        $this->assertEquals(
            file_get_contents(__DIR__.'/../fixtures/resources-target-attributes.xlf'),
            file_get_contents($this->tempDir.'/messages.en_US.xlf')
        );

        unlink($this->tempDir.'/messages.en_US.xlf');
    }

    public function testTargetAttributesMetadataIsSetInFile()
    {
        $catalogue = new MessageCatalogue('en_US');
        $catalogue->add(array(
            'foo' => 'bar',
        ));
        $catalogue->setMetadata('foo', array('target-attributes' => array('state' => 'needs-translation')));

        $tempDir = sys_get_temp_dir();
        $dumper = new XliffFileDumper();
        $dumper->dump($catalogue, array('path' => $tempDir, 'default_locale' => 'fr_FR'));

        $content = <<<EOT
<?xml version="1.0" encoding="utf-8"?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
  <file source-language="fr-FR" target-language="en-US" datatype="plaintext" original="file.ext">
    <body>
      <trans-unit id="acbd18db4cc2f85cedef654fccc4a4d8" resname="foo">
        <source>foo</source>
        <target state="needs-translation">bar</target>
      </trans-unit>
    </body>
  </file>
</xliff>

EOT;

        $this->assertEquals(
            $content,
            file_get_contents($tempDir.'/messages.en_US.xlf')
        );

        unlink($tempDir.'/messages.en_US.xlf');
    }
}
