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
use Symfony\Component\Yaml\Parser;

class ParserTest extends \PHPUnit_Framework_TestCase
{
    protected $parser;

    protected function setUp()
    {
        $this->parser = new Parser();
    }

    protected function tearDown()
    {
        $this->parser = null;
    }

    /**
     * @dataProvider getDataFormSpecifications
     */
    public function testSpecifications($file, $expected, $yaml, $comment)
    {
        if ('escapedCharacters' == $file) {
            if (!function_exists('iconv') && !function_exists('mb_convert_encoding')) {
                $this->markTestSkipped('The iconv and mbstring extensions are not available.');
            }
        }

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

                    $tests[] = array($file, $expected, $test['yaml'], $test['test']);
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
                $this->assertEquals('A YAML file cannot contain tabs as indentation at line 2 (near "'.strpbrk($yaml, "\t").'").', $e->getMessage(), 'YAML files must not contain tabs');
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

    public function getBlockChompingTests()
    {
        $tests = array();

        $yaml = <<<'EOF'
foo: |-
    one
    two

bar: |-
    one
    two

EOF;
        $expected = array(
            'foo' => "one\ntwo",
            'bar' => "one\ntwo",
        );
        $tests['Literal block chomping strip with trailing newline'] = array($expected, $yaml);

        $yaml = <<<'EOF'
foo: |-
    one
    two
bar: |-
    one
    two
EOF;
        $expected = array(
            'foo' => "one\ntwo",
            'bar' => "one\ntwo",
        );
        $tests['Literal block chomping strip without trailing newline'] = array($expected, $yaml);

        $yaml = <<<'EOF'
foo: |
    one
    two

bar: |
    one
    two

EOF;
        $expected = array(
            'foo' => "one\ntwo\n",
            'bar' => "one\ntwo\n",
        );
        $tests['Literal block chomping clip with trailing newline'] = array($expected, $yaml);

        $yaml = <<<'EOF'
foo: |
    one
    two
bar: |
    one
    two
EOF;
        $expected = array(
            'foo' => "one\ntwo\n",
            'bar' => "one\ntwo\n",
        );
        $tests['Literal block chomping clip without trailing newline'] = array($expected, $yaml);

        $yaml = <<<'EOF'
foo: |+
    one
    two

bar: |+
    one
    two

EOF;
        $expected = array(
            'foo' => "one\ntwo\n\n",
            'bar' => "one\ntwo\n\n",
        );
        $tests['Literal block chomping keep with trailing newline'] = array($expected, $yaml);

        $yaml = <<<'EOF'
foo: |+
    one
    two
bar: |+
    one
    two
EOF;
        $expected = array(
            'foo' => "one\ntwo\n",
            'bar' => "one\ntwo\n",
        );
        $tests['Literal block chomping keep without trailing newline'] = array($expected, $yaml);

        $yaml = <<<'EOF'
foo: >-
    one
    two

bar: >-
    one
    two

EOF;
        $expected = array(
            'foo' => "one two",
            'bar' => "one two",
        );
        $tests['Folded block chomping strip with trailing newline'] = array($expected, $yaml);

        $yaml = <<<'EOF'
foo: >-
    one
    two
bar: >-
    one
    two
EOF;
        $expected = array(
            'foo' => "one two",
            'bar' => "one two",
        );
        $tests['Folded block chomping strip without trailing newline'] = array($expected, $yaml);

        $yaml = <<<'EOF'
foo: >
    one
    two

bar: >
    one
    two

EOF;
        $expected = array(
            'foo' => "one two\n",
            'bar' => "one two\n",
        );
        $tests['Folded block chomping clip with trailing newline'] = array($expected, $yaml);

        $yaml = <<<'EOF'
foo: >
    one
    two
bar: >
    one
    two
EOF;
        $expected = array(
            'foo' => "one two\n",
            'bar' => "one two\n",
        );
        $tests['Folded block chomping clip without trailing newline'] = array($expected, $yaml);

        $yaml = <<<'EOF'
foo: >+
    one
    two

bar: >+
    one
    two

EOF;
        $expected = array(
            'foo' => "one two\n\n",
            'bar' => "one two\n\n",
        );
        $tests['Folded block chomping keep with trailing newline'] = array($expected, $yaml);

        $yaml = <<<'EOF'
foo: >+
    one
    two
bar: >+
    one
    two
EOF;
        $expected = array(
            'foo' => "one two\n",
            'bar' => "one two\n",
        );
        $tests['Folded block chomping keep without trailing newline'] = array($expected, $yaml);

        return $tests;
    }

    /**
     * @dataProvider getBlockChompingTests
     */
    public function testBlockChomping($expected, $yaml)
    {
        $this->assertSame($expected, $this->parser->parse($yaml));
    }

    public function testObjectSupportEnabled()
    {
        $input = <<<EOF
foo: !!php/object:O:30:"Symfony\Component\Yaml\Tests\B":1:{s:1:"b";s:3:"foo";}
bar: 1
EOF;
        $this->assertEquals(array('foo' => new B(), 'bar' => 1), $this->parser->parse($input, false, true), '->parse() is able to parse objects');
    }

    public function testObjectSupportDisabledButNoExceptions()
    {
        $input = <<<EOF
foo: !!php/object:O:30:"Symfony\Tests\Component\Yaml\B":1:{s:1:"b";s:3:"foo";}
bar: 1
EOF;

        $this->assertEquals(array('foo' => null, 'bar' => 1), $this->parser->parse($input), '->parse() does not parse objects');
    }

    /**
     * @expectedException \Symfony\Component\Yaml\Exception\ParseException
     */
    public function testObjectsSupportDisabledWithExceptions()
    {
        $this->parser->parse('foo: !!php/object:O:30:"Symfony\Tests\Component\Yaml\B":1:{s:1:"b";s:3:"foo";}', true, false);
    }

    public function testNonUtf8Exception()
    {
        if (!function_exists('mb_detect_encoding') || !function_exists('iconv')) {
            $this->markTestSkipped('Exceptions for non-utf8 charsets require the mb_detect_encoding() and iconv() functions.');

            return;
        }

        $yamls = array(
            iconv("UTF-8", "ISO-8859-1", "foo: 'äöüß'"),
            iconv("UTF-8", "ISO-8859-15", "euro: '€'"),
            iconv("UTF-8", "CP1252", "cp1252: '©ÉÇáñ'")
        );

        foreach ($yamls as $yaml) {
            try {
                $this->parser->parse($yaml);

                $this->fail('charsets other than UTF-8 are rejected.');
            } catch (\Exception $e) {
                 $this->assertInstanceOf('Symfony\Component\Yaml\Exception\ParseException', $e, 'charsets other than UTF-8 are rejected.');
            }
        }
    }

    /**
     *
     * @expectedException \Symfony\Component\Yaml\Exception\ParseException
     *
     */
    public function testUnindentedCollectionException()
    {
        $yaml = <<<EOF

collection:
-item1
-item2
-item3

EOF;

        $this->parser->parse($yaml);
    }

    /**
     * @expectedException \Symfony\Component\Yaml\Exception\ParseException
     */
    public function testSequenceInAMapping()
    {
        Yaml::parse(<<<EOF
yaml:
  hash: me
  - array stuff
EOF
        );
    }

    /**
     * @expectedException \Symfony\Component\Yaml\Exception\ParseException
     */
    public function testMappingInASequence()
    {
        Yaml::parse(<<<EOF
yaml:
  - array stuff
  hash: me
EOF
        );
    }
}

class B
{
    public $b = 'foo';
}
