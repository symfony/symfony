<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\OutputEscaper;

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
        $this->path = __DIR__.'/../../../../fixtures/Symfony/Components/Yaml';
    }

    public function testSpecifications()
    {
        $files = $this->parser->parse(file_get_contents($this->path.'/index.yml'));
        foreach ($files as $file)
        {
            $yamls = file_get_contents($this->path.'/'.$file.'.yml');

            // split YAMLs documents
            foreach (preg_split('/^---( %YAML\:1\.0)?/m', $yamls) as $yaml)
            {
                if (!$yaml)
                {
                    continue;
                }

                $test = $this->parser->parse($yaml);
                if (isset($test['todo']) && $test['todo'])
                {
                    // TODO
                }
                else
                {
                    $expected = var_export(eval('return '.trim($test['php']).';'), true);

                    $this->assertEquals($expected, var_export($this->parser->parse($test['yaml']), true), $test['test']);
                }
            }
        }
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

        foreach ($yamls as $yaml)
        {
            try
            {
                $content = $this->parser->parse($yaml);

                $this->fail('YAML files must not contain tabs');
            }
            catch (\Exception $e)
            {
                $this->assertInstanceOf('\Exception', $e, 'YAML files must not contain tabs');
                $this->assertEquals('A YAML file cannot contain tabs as indentation at line 2 ('.strpbrk($yaml, "\t").').', $e->getMessage(), 'YAML files must not contain tabs');
            }
        }
    }

    public function testObjectsSupport()
    {
        $b = array('foo' => new B(), 'bar' => 1);
        $this->assertEquals($this->parser->parse(<<<EOF
foo: !!php/object:O:40:"Symfony\Tests\Components\OutputEscaper\B":1:{s:1:"b";s:3:"foo";}
bar: 1
EOF
        ), $b, '->parse() is able to dump objects');
    }
}

class B
{
    public $b = 'foo';
}
