<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Yaml;

use Symfony\Components\Yaml\Yaml;
use Symfony\Components\Yaml\Parser;
use Symfony\Components\Yaml\ParserException;

class ParserTest extends \PHPUnit_Framework_TestCase
{
    protected $parser;
    protected $path;

    static public function setUpBeforeClass()
    {
        Yaml::setSpecVersion('1.1');
    }

    public function setUp()
    {
        $this->parser = new Parser();
    }

    /**
     * @dataProvider getDataFormSpecifications
     */
    public function testSpecifications($expected, $yaml, $comment)
    {
        $this->assertEquals($expected, var_export($this->parser->parse($yaml), true), $comment);
    }

    public function getDataFormSpecifications()
    {
        $parser = new Parser();
        $path = __DIR__.'/Fixtures';

        $tests = array();
        $files = $parser->parse(file_get_contents($path.'/index.yml'));
        foreach ($files as $file) {
            $yamls = file_get_contents($path.'/'.$file.'.yml');

            // split YAMLs documents
            foreach (preg_split('/^---( %YAML\:1\.0)?/m', $yamls) as $yaml) {
                if (!$yaml) {
                    continue;
                }

                $test = $parser->parse($yaml);
                if (isset($test['todo']) && $test['todo']) {
                    // TODO
                } else {
                    $expected = var_export(eval('return '.trim($test['php']).';'), true);

                    $tests[] = array($expected, $test['yaml'], $test['test']);
                }
            }
        }

        return $tests;
    }

    public function testTabsInYaml()
    {
        // test tabs in YAML
        $yamls = array(
            "foo:\n	bar",
            "foo:\n 	bar",
            "foo:\n	 bar",
            "foo:\n 	 bar",
        );

        foreach ($yamls as $yaml) {
            try {
                $content = $this->parser->parse($yaml);

                $this->fail('YAML files must not contain tabs');
            } catch (\Exception $e) {
                $this->assertInstanceOf('\Exception', $e, 'YAML files must not contain tabs');
                $this->assertEquals('A YAML file cannot contain tabs as indentation at line 2 ('.strpbrk($yaml, "\t").').', $e->getMessage(), 'YAML files must not contain tabs');
            }
        }
    }

    public function testEndOfTheDocumentMarker()
    {
        $yaml = <<<EOF
--- %YAML:1.0
foo
...
EOF;

        $this->assertEquals('foo', $this->parser->parse($yaml));
    }

    public function testObjectsSupport()
    {
        $b = array('foo' => new B(), 'bar' => 1);
        $this->assertEquals($this->parser->parse(<<<EOF
foo: !!php/object:O:31:"Symfony\Tests\Components\Yaml\B":1:{s:1:"b";s:3:"foo";}
bar: 1
EOF
        ), $b, '->parse() is able to dump objects');
    }
}

class B
{
    public $b = 'foo';
}
