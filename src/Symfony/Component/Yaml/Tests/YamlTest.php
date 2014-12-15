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
    }

    public function testParseFromFile()
    {
        $filename = __DIR__.'/Fixtures/index.yml';
        $contents = file_get_contents($filename);
        $parsedByFilename = Yaml::parse($filename);
        $parsedByContents = Yaml::parse($contents);
        $this->assertEquals($parsedByFilename, $parsedByContents);
    }
}
