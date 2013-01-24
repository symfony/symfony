<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Yaml\Tests;

use Symfony\Component\Yaml\Yaml;

class YamlTest extends \PHPUnit_Framework_TestCase
{

    public function testParseAndDump()
    {
        $data = array('lorem' => 'ipsum', 'dolor' => 'sit');
        $yml = Yaml::dump($data);
        $parsed = Yaml::parse($yml);
        $this->assertEquals($data, $parsed);

        $filename = __DIR__.'/Fixtures/index.yml';
        $contents = file_get_contents($filename);
        $parsedByFilename = Yaml::parse($filename);
        $parsedByContents = Yaml::parse($contents);
        $this->assertEquals($parsedByFilename, $parsedByContents);
    }

    public function testEmbededPhp()
    {
        $filename = __DIR__.'/Fixtures/embededPhp.yml';
        Yaml::enablePhpParsing();
        $parsed = Yaml::parse($filename);
        $this->assertEquals(array('value' => 6), $parsed);
    }

    public function testParseObjectWithScalar()
    {
        $yaml = 'test: { key: "value", date: !!php/object:O:8:"DateTime":3:{s:4:"date";s:19:"2012-12-25 00:00:00";s:13:"timezone_type";i:3;s:8:"timezone";s:13:"Europe/London";} }';
        $date = new \DateTime('2012-12-25 00:00:00', new \DateTimeZone('Europe/London'));
        $expected = array('test' => array('key' => 'value', 'date' => $date));

        $this->assertEquals($expected, Yaml::parse($yaml));
    }

}
