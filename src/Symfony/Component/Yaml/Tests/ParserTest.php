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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectUserDeprecationMessageTrait;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml;

class ParserTest extends TestCase
{
    use ExpectUserDeprecationMessageTrait;

    private ?Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    protected function tearDown(): void
    {
        $this->parser = null;

        chmod(__DIR__.'/Fixtures/not_readable.yml', 0644);
    }

    public function testTopLevelNumber()
    {
        $yml = '5';
        $data = $this->parser->parse($yml);
        $expected = 5;
        $this->assertSameData($expected, $data);
    }

    public function testTopLevelNull()
    {
        $yml = 'null';
        $data = $this->parser->parse($yml);
        $expected = null;
        $this->assertSameData($expected, $data);
    }

    public function testEmptyValueInExpandedMappingIsSupported()
    {
        $yml = <<<'YAML'
foo:
    bar:
    baz: qux
YAML;

        $data = $this->parser->parse($yml);
        $expected = ['foo' => ['bar' => null, 'baz' => 'qux']];
        $this->assertSameData($expected, $data);
    }

    public function testEmptyValueInExpandedSequenceIsSupported()
    {
        $yml = <<<'YAML'
foo:
    - bar
    -
    - baz
YAML;

        $data = $this->parser->parse($yml);
        $expected = ['foo' => ['bar', null, 'baz']];
        $this->assertSameData($expected, $data);
    }

    public function testTaggedValueTopLevelNumber()
    {
        $yml = '!number 5';
        $data = $this->parser->parse($yml, Yaml::PARSE_CUSTOM_TAGS);
        $expected = new TaggedValue('number', 5);
        $this->assertSameData($expected, $data);
    }

    public function testTaggedValueTopLevelNull()
    {
        $yml = '!tag null';
        $data = $this->parser->parse($yml, Yaml::PARSE_CUSTOM_TAGS);

        $this->assertSameData(new TaggedValue('tag', null), $data);
    }

    public function testTaggedValueTopLevelString()
    {
        $yml = '!user barbara';
        $data = $this->parser->parse($yml, Yaml::PARSE_CUSTOM_TAGS);
        $expected = new TaggedValue('user', 'barbara');
        $this->assertSameData($expected, $data);
    }

    public function testTaggedValueTopLevelAssocInline()
    {
        $yml = '!user { name: barbara }';
        $data = $this->parser->parse($yml, Yaml::PARSE_CUSTOM_TAGS);
        $expected = new TaggedValue('user', ['name' => 'barbara']);
        $this->assertSameData($expected, $data);
    }

    public function testTaggedValueTopLevelAssoc()
    {
        $yml = <<<'YAML'
!user
name: barbara
YAML;
        $data = $this->parser->parse($yml, Yaml::PARSE_CUSTOM_TAGS);
        $expected = new TaggedValue('user', ['name' => 'barbara']);
        $this->assertSameData($expected, $data);
    }

    public function testTaggedValueTopLevelList()
    {
        $yml = <<<'YAML'
!users
- barbara
YAML;
        $data = $this->parser->parse($yml, Yaml::PARSE_CUSTOM_TAGS);
        $expected = new TaggedValue('users', ['barbara']);
        $this->assertSameData($expected, $data);
    }

    public function testTaggedTextAsListItem()
    {
        $yml = <<<'YAML'
- !text |
  first line
YAML;
        // @todo Fix the parser, eliminate this exception.
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Unable to parse at line 2 (near "!text |").');
        $this->parser->parse($yml, Yaml::PARSE_CUSTOM_TAGS);
    }

    /**
     * @dataProvider getDataFormSpecifications
     */
    public function testSpecifications($expected, $yaml, $comment)
    {
        $this->assertEquals($expected, var_export($this->parser->parse($yaml), true), $comment);
    }

    public static function getDataFormSpecifications()
    {
        return self::loadTestsFromFixtureFiles('index.yml');
    }

    public static function getNonStringMappingKeysData()
    {
        return self::loadTestsFromFixtureFiles('nonStringKeys.yml');
    }

    /**
     * @dataProvider invalidIndentation
     */
    public function testTabsAsIndentationInYaml(string $given, string $expectedMessage)
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage($expectedMessage);
        $this->parser->parse($given);
    }

    public static function invalidIndentation(): array
    {
        return [
            [
                "foo:\n\tbar",
                "A YAML file cannot contain tabs as indentation at line 2 (near \"\tbar\").",
            ],
            [
                "foo:\n \tbar",
                "A YAML file cannot contain tabs as indentation at line 2 (near \"\tbar\").",
            ],
            [
                "foo:\n\t bar",
                "A YAML file cannot contain tabs as indentation at line 2 (near \"\t bar\").",
            ],
            [
                "foo:\n \t bar",
                "A YAML file cannot contain tabs as indentation at line 2 (near \"\t bar\").",
            ],
        ];
    }

    public function testParserIsStateless()
    {
        $yamlString = '# translations/messages.en.yaml

';
        $this->parser->parse($yamlString);
        $this->parser->parse($yamlString);

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage("A YAML file cannot contain tabs as indentation at line 2 (near \"\tabc\")");

        $this->parser->parse("abc:\n\tabc");
    }

    /**
     * @dataProvider validTokenSeparators
     */
    public function testValidTokenSeparation(string $given, array $expected)
    {
        $actual = $this->parser->parse($given);
        $this->assertSameData($expected, $actual);
    }

    public static function validTokenSeparators(): array
    {
        return [
            [
                'foo: bar',
                ['foo' => 'bar'],
            ],
            [
                "foo:\tbar",
                ['foo' => 'bar'],
            ],
            [
                "foo: \tbar",
                ['foo' => 'bar'],
            ],
            [
                "foo:\t bar",
                ['foo' => 'bar'],
            ],
        ];
    }

    public function testEndOfTheDocumentMarker()
    {
        $yaml = <<<'EOF'
--- %YAML:1.0
foo
...
EOF;

        $this->assertEquals('foo', $this->parser->parse($yaml));
    }

    public static function getBlockChompingTests()
    {
        $tests = [];

        $yaml = <<<'EOF'
foo: |-
    one
    two
bar: |-
    one
    two

EOF;
        $expected = [
            'foo' => "one\ntwo",
            'bar' => "one\ntwo",
        ];
        $tests['Literal block chomping strip with single trailing newline'] = [$expected, $yaml];

        $yaml = <<<'EOF'
foo: |-
    one
    two

bar: |-
    one
    two


EOF;
        $expected = [
            'foo' => "one\ntwo",
            'bar' => "one\ntwo",
        ];
        $tests['Literal block chomping strip with multiple trailing newlines'] = [$expected, $yaml];

        $yaml = <<<'EOF'
{}


EOF;
        $expected = [];
        $tests['Literal block chomping strip with multiple trailing newlines after a 1-liner'] = [$expected, $yaml];

        $yaml = <<<'EOF'
foo: |-
    one
    two
bar: |-
    one
    two
EOF;
        $expected = [
            'foo' => "one\ntwo",
            'bar' => "one\ntwo",
        ];
        $tests['Literal block chomping strip without trailing newline'] = [$expected, $yaml];

        $yaml = <<<'EOF'
foo: |
    one
    two
bar: |
    one
    two

EOF;
        $expected = [
            'foo' => "one\ntwo\n",
            'bar' => "one\ntwo\n",
        ];
        $tests['Literal block chomping clip with single trailing newline'] = [$expected, $yaml];

        $yaml = <<<'EOF'
foo: |
    one
    two

bar: |
    one
    two


EOF;
        $expected = [
            'foo' => "one\ntwo\n",
            'bar' => "one\ntwo\n",
        ];
        $tests['Literal block chomping clip with multiple trailing newlines'] = [$expected, $yaml];

        $yaml = <<<'EOF'
foo:
- bar: |
    one

    two
EOF;
        $expected = [
            'foo' => [
                [
                    'bar' => "one\n\ntwo",
                ],
            ],
        ];
        $tests['Literal block chomping clip with embedded blank line inside unindented collection'] = [$expected, $yaml];

        $yaml = <<<'EOF'
foo: |
    one
    two
bar: |
    one
    two
EOF;
        $expected = [
            'foo' => "one\ntwo\n",
            'bar' => "one\ntwo",
        ];
        $tests['Literal block chomping clip without trailing newline'] = [$expected, $yaml];

        $yaml = <<<'EOF'
foo: |+
    one
    two
bar: |+
    one
    two

EOF;
        $expected = [
            'foo' => "one\ntwo\n",
            'bar' => "one\ntwo\n",
        ];
        $tests['Literal block chomping keep with single trailing newline'] = [$expected, $yaml];

        $yaml = <<<'EOF'
foo: |+
    one
    two

bar: |+
    one
    two


EOF;
        $expected = [
            'foo' => "one\ntwo\n\n",
            'bar' => "one\ntwo\n\n",
        ];
        $tests['Literal block chomping keep with multiple trailing newlines'] = [$expected, $yaml];

        $yaml = <<<'EOF'
foo: |+
    one
    two
bar: |+
    one
    two
EOF;
        $expected = [
            'foo' => "one\ntwo\n",
            'bar' => "one\ntwo",
        ];
        $tests['Literal block chomping keep without trailing newline'] = [$expected, $yaml];

        $yaml = <<<'EOF'
foo: >-
    one
    two
bar: >-
    one
    two

EOF;
        $expected = [
            'foo' => 'one two',
            'bar' => 'one two',
        ];
        $tests['Folded block chomping strip with single trailing newline'] = [$expected, $yaml];

        $yaml = <<<'EOF'
foo: >-
    one
    two

bar: >-
    one
    two


EOF;
        $expected = [
            'foo' => 'one two',
            'bar' => 'one two',
        ];
        $tests['Folded block chomping strip with multiple trailing newlines'] = [$expected, $yaml];

        $yaml = <<<'EOF'
foo: >-
    one
    two
bar: >-
    one
    two
EOF;
        $expected = [
            'foo' => 'one two',
            'bar' => 'one two',
        ];
        $tests['Folded block chomping strip without trailing newline'] = [$expected, $yaml];

        $yaml = <<<'EOF'
foo: >
    one
    two
bar: >
    one
    two

EOF;
        $expected = [
            'foo' => "one two\n",
            'bar' => "one two\n",
        ];
        $tests['Folded block chomping clip with single trailing newline'] = [$expected, $yaml];

        $yaml = <<<'EOF'
foo: >
    one
    two

bar: >
    one
    two


EOF;
        $expected = [
            'foo' => "one two\n",
            'bar' => "one two\n",
        ];
        $tests['Folded block chomping clip with multiple trailing newlines'] = [$expected, $yaml];

        $yaml = <<<'EOF'
foo: >
    one
    two
bar: >
    one
    two
EOF;
        $expected = [
            'foo' => "one two\n",
            'bar' => 'one two',
        ];
        $tests['Folded block chomping clip without trailing newline'] = [$expected, $yaml];

        $yaml = <<<'EOF'
foo: >+
    one
    two
bar: >+
    one
    two

EOF;
        $expected = [
            'foo' => "one two\n",
            'bar' => "one two\n",
        ];
        $tests['Folded block chomping keep with single trailing newline'] = [$expected, $yaml];

        $yaml = <<<'EOF'
foo: >+
    one
    two

bar: >+
    one
    two


EOF;
        $expected = [
            'foo' => "one two\n\n",
            'bar' => "one two\n\n",
        ];
        $tests['Folded block chomping keep with multiple trailing newlines'] = [$expected, $yaml];

        $yaml = <<<'EOF'
foo: >+
    one
    two
bar: >+
    one
    two
EOF;
        $expected = [
            'foo' => "one two\n",
            'bar' => 'one two',
        ];
        $tests['Folded block chomping keep without trailing newline'] = [$expected, $yaml];

        return $tests;
    }

    /**
     * @dataProvider getBlockChompingTests
     */
    public function testBlockChomping($expected, $yaml)
    {
        $this->assertSame($expected, $this->parser->parse($yaml));
    }

    /**
     * Regression test for issue #7989.
     *
     * @see https://github.com/symfony/symfony/issues/7989
     */
    public function testBlockLiteralWithLeadingNewlines()
    {
        $yaml = <<<'EOF'
foo: |-


    bar

EOF;
        $expected = [
            'foo' => "\n\nbar",
        ];

        $this->assertSame($expected, $this->parser->parse($yaml));
    }

    public function testObjectSupportEnabled()
    {
        $input = <<<'EOF'
foo: !php/object O:30:"Symfony\Component\Yaml\Tests\B":1:{s:1:"b";s:3:"foo";}
bar: 1
EOF;
        $this->assertSameData(['foo' => new B(), 'bar' => 1], $this->parser->parse($input, Yaml::PARSE_OBJECT), '->parse() is able to parse objects');
    }

    public function testObjectSupportDisabledButNoExceptions()
    {
        $input = <<<'EOF'
foo: !php/object O:30:"Symfony\Tests\Component\Yaml\B":1:{s:1:"b";s:3:"foo";}
bar: 1
EOF;
        $this->assertSameData(['foo' => null, 'bar' => 1], $this->parser->parse($input), '->parse() does not parse objects');
    }

    /**
     * @dataProvider getObjectForMapTests
     */
    public function testObjectForMap($yaml, $expected)
    {
        $flags = Yaml::PARSE_OBJECT_FOR_MAP;

        $this->assertSameData($expected, $this->parser->parse($yaml, $flags));
    }

    public static function getObjectForMapTests()
    {
        $tests = [];

        $yaml = <<<'EOF'
foo:
    fiz: [cat]
EOF;
        $expected = new \stdClass();
        $expected->foo = new \stdClass();
        $expected->foo->fiz = ['cat'];
        $tests['mapping'] = [$yaml, $expected];

        $yaml = '{ "foo": "bar", "fiz": "cat" }';
        $expected = new \stdClass();
        $expected->foo = 'bar';
        $expected->fiz = 'cat';
        $tests['inline-mapping'] = [$yaml, $expected];

        $yaml = "foo: bar\nbaz: foobar";
        $expected = new \stdClass();
        $expected->foo = 'bar';
        $expected->baz = 'foobar';
        $tests['object-for-map-is-applied-after-parsing'] = [$yaml, $expected];

        $yaml = <<<'EOT'
array:
  - key: one
  - key: two
EOT;
        $expected = new \stdClass();
        $expected->array = [];
        $expected->array[0] = new \stdClass();
        $expected->array[0]->key = 'one';
        $expected->array[1] = new \stdClass();
        $expected->array[1]->key = 'two';
        $tests['nest-map-and-sequence'] = [$yaml, $expected];

        $yaml = <<<'YAML'
map:
  1: one
  2: two
YAML;
        $expected = new \stdClass();
        $expected->map = new \stdClass();
        $expected->map->{1} = 'one';
        $expected->map->{2} = 'two';
        $tests['numeric-keys'] = [$yaml, $expected];

        $yaml = <<<'YAML'
map:
  '0': one
  '1': two
YAML;
        $expected = new \stdClass();
        $expected->map = new \stdClass();
        $expected->map->{0} = 'one';
        $expected->map->{1} = 'two';
        $tests['zero-indexed-numeric-keys'] = [$yaml, $expected];

        return $tests;
    }

    public function testObjectsSupportDisabledWithExceptions()
    {
        $yaml = <<<'EOF'
foo: !php/object:O:30:"Symfony\Tests\Component\Yaml\B":1:{s:1:"b";s:3:"foo";}
bar: 1
EOF;

        $this->expectException(ParseException::class);

        $this->parser->parse($yaml, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
    }

    public function testMappingKeyInMultiLineStringThrowsException()
    {
        $yaml = <<<'EOF'
data:
    dbal:wrong
        default_connection: monolith
EOF;

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Mapping values are not allowed in multi-line blocks at line 2 (near "dbal:wrong").');

        $this->parser->parse($yaml);
    }

    public function testCanParseContentWithTrailingSpaces()
    {
        $yaml = "items:  \n  foo: bar";

        $expected = [
            'items' => ['foo' => 'bar'],
        ];

        $this->assertSame($expected, $this->parser->parse($yaml));
    }

    /**
     * @requires extension iconv
     */
    public function testNonUtf8Exception()
    {
        $yamls = [
            iconv('UTF-8', 'ISO-8859-1', "foo: 'äöüß'"),
            iconv('UTF-8', 'ISO-8859-15', "euro: '€'"),
            iconv('UTF-8', 'CP1252', "cp1252: '©ÉÇáñ'"),
        ];

        foreach ($yamls as $yaml) {
            try {
                $this->parser->parse($yaml);

                $this->fail('charsets other than UTF-8 are rejected.');
            } catch (\Exception $e) {
                $this->assertInstanceOf(ParseException::class, $e, 'charsets other than UTF-8 are rejected.');
            }
        }
    }

    public function testUnindentedCollectionException()
    {
        $yaml = <<<'EOF'

collection:
-item1
-item2
-item3

EOF;

        $this->expectException(ParseException::class);

        $this->parser->parse($yaml);
    }

    public function testShortcutKeyUnindentedCollectionException()
    {
        $yaml = <<<'EOF'

collection:
-  key: foo
  foo: bar

EOF;

        $this->expectException(ParseException::class);

        $this->parser->parse($yaml);
    }

    public function testMultipleDocumentsNotSupportedException()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessageMatches('/^Multiple documents are not supported.+/');
        Yaml::parse(<<<'EOL'
# Ranking of 1998 home runs
---
- Mark McGwire
- Sammy Sosa
- Ken Griffey

# Team ranking
---
- Chicago Cubs
- St Louis Cardinals
EOL
        );
    }

    public function testSequenceInAMapping()
    {
        $this->expectException(ParseException::class);
        Yaml::parse(<<<'EOF'
yaml:
  hash: me
  - array stuff
EOF
        );
    }

    public function testSequenceInMappingStartedBySingleDashLine()
    {
        $yaml = <<<'EOT'
a:
-
  b:
  -
    bar: baz
- foo
d: e
EOT;
        $expected = [
            'a' => [
                [
                    'b' => [
                        [
                            'bar' => 'baz',
                        ],
                    ],
                ],
                'foo',
            ],
            'd' => 'e',
        ];

        $this->assertSame($expected, $this->parser->parse($yaml));
    }

    public function testSequenceFollowedByCommentEmbeddedInMapping()
    {
        $yaml = <<<'EOT'
a:
    b:
        - c
# comment
    d: e
EOT;
        $expected = [
            'a' => [
                'b' => ['c'],
                'd' => 'e',
            ],
        ];

        $this->assertSame($expected, $this->parser->parse($yaml));
    }

    public function testNonStringFollowedByCommentEmbeddedInMapping()
    {
        $yaml = <<<'EOT'
a:
    b:
        {}
# comment
    d:
        1.1
# another comment
EOT;
        $expected = [
            'a' => [
                'b' => [],
                'd' => 1.1,
            ],
        ];

        $this->assertSame($expected, $this->parser->parse($yaml));
    }

    public static function getParseExceptionNotAffectedMultiLineStringLastResortParsing()
    {
        $tests = [];

        $yaml = <<<'EOT'
a
    b:
EOT;
        $tests['parse error on first line'] = [$yaml];

        $yaml = <<<'EOT'
a

b
    c:
EOT;
        $tests['parse error due to inconsistent indentation'] = [$yaml];

        $yaml = <<<'EOT'
 &  *  !  |  >  '  "  %  @  ` #, { asd a;sdasd }-@^qw3
EOT;
        $tests['symfony/symfony/issues/22967#issuecomment-322067742'] = [$yaml];

        return $tests;
    }

    /**
     * @dataProvider getParseExceptionNotAffectedMultiLineStringLastResortParsing
     */
    public function testParseExceptionNotAffectedByMultiLineStringLastResortParsing($yaml)
    {
        $this->expectException(ParseException::class);
        $this->parser->parse($yaml);
    }

    public function testMultiLineStringLastResortParsing()
    {
        $yaml = <<<'EOT'
test:
  You can have things that don't look like strings here
  true
  yes you can
EOT;
        $expected = [
            'test' => 'You can have things that don\'t look like strings here true yes you can',
        ];

        $this->assertSame($expected, $this->parser->parse($yaml));

        $yaml = <<<'EOT'
a:
    b
       c
EOT;
        $expected = [
            'a' => 'b c',
        ];

        $this->assertSame($expected, $this->parser->parse($yaml));
    }

    public function testMappingInASequence()
    {
        $this->expectException(ParseException::class);
        Yaml::parse(<<<'EOF'
yaml:
  - array stuff
  hash: me
EOF
        );
    }

    public function testScalarInSequence()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('missing colon');
        Yaml::parse(<<<'EOF'
foo:
    - bar
"missing colon"
    foo: bar
EOF
        );
    }

    /**
     * > It is an error for two equal keys to appear in the same mapping node.
     * > In such a case the YAML processor may continue, ignoring the second
     * > "key: value" pair and issuing an appropriate warning. This strategy
     * > preserves a consistent information model for one-pass and random access
     * > applications.
     *
     * @see http://yaml.org/spec/1.2/spec.html#id2759572
     * @see http://yaml.org/spec/1.1/#id932806
     */
    public function testMappingDuplicateKeyBlock()
    {
        $input = <<<'EOD'
parent:
    child: first
    child: duplicate
parent:
    child: duplicate
    child: duplicate
EOD;

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Duplicate key "child" detected');

        Yaml::parse($input);
    }

    public function testMappingDuplicateKeyFlow()
    {
        $input = <<<'EOD'
parent: { child: first, child: duplicate }
parent: { child: duplicate, child: duplicate }
EOD;

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Duplicate key "child" detected');

        Yaml::parse($input);
    }

    /**
     * @dataProvider getParseExceptionOnDuplicateData
     */
    public function testParseExceptionOnDuplicate($input, $duplicateKey, $lineNumber)
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage(\sprintf('Duplicate key "%s" detected at line %d', $duplicateKey, $lineNumber));

        Yaml::parse($input);
    }

    public static function getParseExceptionOnDuplicateData()
    {
        $tests = [];

        $yaml = <<<EOD
parent: { child: first, child: duplicate }
EOD;
        $tests[] = [$yaml, 'child', 1];

        $yaml = <<<EOD
parent:
  child: first,
  child: duplicate
EOD;
        $tests[] = [$yaml, 'child', 3];

        $yaml = <<<EOD
parent: { child: foo }
parent: { child: bar }
EOD;
        $tests[] = [$yaml, 'parent', 2];

        $yaml = <<<EOD
parent: { child_mapping: { value: bar},  child_mapping: { value: bar} }
EOD;
        $tests[] = [$yaml, 'child_mapping', 1];

        $yaml = <<<EOD
parent:
  child_mapping:
    value: bar
  child_mapping:
    value: bar
EOD;
        $tests[] = [$yaml, 'child_mapping', 4];

        $yaml = <<<EOD
parent: { child_sequence: ['key1', 'key2', 'key3'],  child_sequence: ['key1', 'key2', 'key3'] }
EOD;
        $tests[] = [$yaml, 'child_sequence', 1];

        $yaml = <<<EOD
parent:
  child_sequence:
    - key1
    - key2
    - key3
  child_sequence:
    - key1
    - key2
    - key3
EOD;
        $tests[] = [$yaml, 'child_sequence', 6];

        return $tests;
    }

    /**
     * @group legacy
     */
    public function testNullAsDuplicatedData()
    {
        $this->expectUserDeprecationMessage('Since symfony/yaml 7.2: Duplicate key "child" detected on line 4 whilst parsing YAML. Silent handling of duplicate mapping keys in YAML is deprecated and will throw a ParseException in 8.0.');

        $yaml = <<<EOD
parent:
  child:
  child2:
  child:
EOD;

        Yaml::parse($yaml);
    }

    public function testEmptyValue()
    {
        $input = <<<'EOF'
hash:
EOF;

        $this->assertSame(['hash' => null], Yaml::parse($input));
    }

    public function testCommentAtTheRootIndent()
    {
        $this->assertSame([
            'services' => [
                'app.foo_service' => [
                    'class' => 'Foo',
                ],
                'app/bar_service' => [
                    'class' => 'Bar',
                ],
            ],
        ], Yaml::parse(<<<'EOF'
# comment 1
services:
# comment 2
    # comment 3
    app.foo_service:
        class: Foo
# comment 4
    # comment 5
    app/bar_service:
        class: Bar
EOF
        ));
    }

    public function testStringBlockWithComments()
    {
        $this->assertSame(['content' => <<<'EOT'
# comment 1
header

    # comment 2
    <body>
        <h1>title</h1>
    </body>

footer # comment3
EOT
        ], Yaml::parse(<<<'EOF'
content: |
    # comment 1
    header

        # comment 2
        <body>
            <h1>title</h1>
        </body>

    footer # comment3
EOF
        ));
    }

    public function testFoldedStringBlockWithComments()
    {
        $this->assertSame([['content' => <<<'EOT'
# comment 1
header

    # comment 2
    <body>
        <h1>title</h1>
    </body>

footer # comment3
EOT
        ]], Yaml::parse(<<<'EOF'
-
    content: |
        # comment 1
        header

            # comment 2
            <body>
                <h1>title</h1>
            </body>

        footer # comment3
EOF
        ));
    }

    public function testNestedFoldedStringBlockWithComments()
    {
        $this->assertSame([[
            'title' => 'some title',
            'content' => <<<'EOT'
# comment 1
header

    # comment 2
    <body>
        <h1>title</h1>
    </body>

footer # comment3
EOT,
        ]], Yaml::parse(<<<'EOF'
-
    title: some title
    content: |
        # comment 1
        header

            # comment 2
            <body>
                <h1>title</h1>
            </body>

        footer # comment3
EOF
        ));
    }

    public function testReferenceResolvingInInlineStrings()
    {
        $this->assertSame([
            'var' => 'var-value',
            'scalar' => 'var-value',
            'list' => ['var-value'],
            'list_in_list' => [['var-value']],
            'map_in_list' => [['key' => 'var-value']],
            'embedded_mapping' => [['key' => 'var-value']],
            'map' => ['key' => 'var-value'],
            'list_in_map' => ['key' => ['var-value']],
            'map_in_map' => ['foo' => ['bar' => 'var-value']],
            'foo' => ['bar' => 'baz'],
            'bar' => ['foo' => 'baz'],
            'baz' => ['foo'],
            'foobar' => ['foo'],
        ], Yaml::parse(<<<'EOF'
var:  &var var-value
scalar: *var
list: [ *var ]
list_in_list: [[ *var ]]
map_in_list: [ { key: *var } ]
embedded_mapping: [ key: *var ]
map: { key: *var }
list_in_map: { key: [*var] }
map_in_map: { foo: { bar: *var } }
foo: { bar: &baz baz }
bar: { foo: *baz }
baz: [ &foo foo ]
foobar: [ *foo ]
EOF
        ));
    }

    public function testYamlDirective()
    {
        $yaml = <<<'EOF'
%YAML 1.2
---
foo: 1
bar: 2
EOF;
        $this->assertSame(['foo' => 1, 'bar' => 2], $this->parser->parse($yaml));
    }

    public function testFloatKeys()
    {
        $yaml = <<<'EOF'
foo:
    1.2: "bar"
    1.3: "baz"
EOF;

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Numeric keys are not supported. Quote your evaluable mapping keys instead');

        $this->parser->parse($yaml);
    }

    public function testBooleanKeys()
    {
        $yaml = <<<'EOF'
true: foo
false: bar
EOF;

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Non-string keys are not supported. Quote your evaluable mapping keys instead');

        $this->parser->parse($yaml);
    }

    public function testExplicitStringCasting()
    {
        $yaml = <<<'EOF'
'1.2': "bar"
!!str 1.3: "baz"

'true': foo
!!str false: bar

!!str null: 'null'
'~': 'null'
EOF;

        $expected = [
            '1.2' => 'bar',
            '1.3' => 'baz',
            'true' => 'foo',
            'false' => 'bar',
            'null' => 'null',
            '~' => 'null',
        ];

        $this->assertSame($expected, $this->parser->parse($yaml));
    }

    public function testColonInMappingValueException()
    {
        $yaml = <<<'EOF'
foo: bar: baz
EOF;

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('A colon cannot be used in an unquoted mapping value');

        $this->parser->parse($yaml);
    }

    public function testColonInMappingValueExceptionNotTriggeredByColonInComment()
    {
        $yaml = <<<'EOT'
foo:
    bar: foobar # Note: a comment after a colon
EOT;

        $this->assertSame(['foo' => ['bar' => 'foobar']], $this->parser->parse($yaml));
    }

    /**
     * @dataProvider getCommentLikeStringInScalarBlockData
     */
    public function testCommentLikeStringsAreNotStrippedInBlockScalars($yaml, $expectedParserResult)
    {
        $this->assertSame($expectedParserResult, $this->parser->parse($yaml));
    }

    public static function getCommentLikeStringInScalarBlockData()
    {
        $tests = [];

        $yaml = <<<'EOT'
pages:
    -
        title: some title
        content: |
            # comment 1
            header

                # comment 2
                <body>
                    <h1>title</h1>
                </body>

            footer # comment3
EOT;
        $expected = [
            'pages' => [
                [
                    'title' => 'some title',
                    'content' => <<<'EOT'
# comment 1
header

    # comment 2
    <body>
        <h1>title</h1>
    </body>

footer # comment3
EOT
                    ,
                ],
            ],
        ];
        $tests[] = [$yaml, $expected];

        $yaml = <<<'EOT'
test: |
    foo
    # bar
    baz
collection:
    - one: |
        foo
        # bar
        baz
    - two: |
        foo
        # bar
        baz
EOT;
        $expected = [
            'test' => <<<'EOT'
foo
# bar
baz

EOT
            ,
            'collection' => [
                [
                    'one' => <<<'EOT'
foo
# bar
baz

EOT
                    ,
                ],
                [
                    'two' => <<<'EOT'
foo
# bar
baz
EOT
                    ,
                ],
            ],
        ];
        $tests[] = [$yaml, $expected];

        $yaml = <<<'EOT'
foo:
  bar:
    scalar-block: >
      line1
      line2>
  baz:
# comment
    foobar: ~
EOT;
        $expected = [
            'foo' => [
                'bar' => [
                    'scalar-block' => "line1 line2>\n",
                ],
                'baz' => [
                    'foobar' => null,
                ],
            ],
        ];
        $tests[] = [$yaml, $expected];

        $yaml = <<<'EOT'
a:
    b: hello
#    c: |
#        first row
#        second row
    d: hello
EOT;
        $expected = [
            'a' => [
                'b' => 'hello',
                'd' => 'hello',
            ],
        ];
        $tests[] = [$yaml, $expected];

        return $tests;
    }

    public function testBlankLinesAreParsedAsNewLinesInFoldedBlocks()
    {
        $yaml = <<<'EOT'
test: >
    <h2>A heading</h2>

    <ul>
    <li>a list</li>
    <li>may be a good example</li>
    </ul>
EOT;

        $this->assertSame(
            [
                'test' => <<<'EOT'
<h2>A heading</h2>
<ul> <li>a list</li> <li>may be a good example</li> </ul>
EOT
                ,
            ],
            $this->parser->parse($yaml)
        );
    }

    public function testAdditionallyIndentedLinesAreParsedAsNewLinesInFoldedBlocks()
    {
        $yaml = <<<'EOT'
test: >
    <h2>A heading</h2>

    <ul>
      <li>a list</li>
      <li>may be a good example</li>
    </ul>
EOT;

        $this->assertSame(
            [
                'test' => <<<'EOT'
<h2>A heading</h2>
<ul>
  <li>a list</li>
  <li>may be a good example</li>
</ul>
EOT
                ,
            ],
            $this->parser->parse($yaml)
        );
    }

    /**
     * @dataProvider getBinaryData
     */
    public function testParseBinaryData($data)
    {
        $this->assertSame(['data' => 'Hello world'], $this->parser->parse($data));
    }

    public static function getBinaryData()
    {
        return [
            'enclosed with double quotes' => ['data: !!binary "SGVsbG8gd29ybGQ="'],
            'enclosed with single quotes' => ["data: !!binary 'SGVsbG8gd29ybGQ='"],
            'containing spaces' => ['data: !!binary  "SGVs bG8gd 29ybGQ="'],
            'in block scalar' => [
                <<<'EOT'
data: !!binary |
    SGVsbG8gd29ybGQ=
EOT,
            ],
            'containing spaces in block scalar' => [
                <<<'EOT'
data: !!binary |
    SGVs bG8gd 29ybGQ=
EOT,
            ],
        ];
    }

    /**
     * @dataProvider getInvalidBinaryData
     */
    public function testParseInvalidBinaryData($data, $expectedMessage)
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessageMatches($expectedMessage);

        $this->parser->parse($data);
    }

    public static function getInvalidBinaryData()
    {
        return [
            'length not a multiple of four' => ['data: !!binary "SGVsbG8d29ybGQ="', '/The normalized base64 encoded data \(data without whitespace characters\) length must be a multiple of four \(\d+ bytes given\)/'],
            'invalid characters' => ['!!binary "SGVsbG8#d29ybGQ="', '/The base64 encoded data \(.*\) contains invalid characters/'],
            'too many equals characters' => ['data: !!binary "SGVsbG8gd29yb==="', '/The base64 encoded data \(.*\) contains invalid characters/'],
            'misplaced equals character' => ['data: !!binary "SGVsbG8gd29ybG=Q"', '/The base64 encoded data \(.*\) contains invalid characters/'],
            'length not a multiple of four in block scalar' => [
                <<<'EOT'
data: !!binary |
    SGVsbG8d29ybGQ=
EOT
                ,
                '/The normalized base64 encoded data \(data without whitespace characters\) length must be a multiple of four \(\d+ bytes given\)/',
            ],
            'invalid characters in block scalar' => [
                <<<'EOT'
data: !!binary |
    SGVsbG8#d29ybGQ=
EOT
                ,
                '/The base64 encoded data \(.*\) contains invalid characters/',
            ],
            'too many equals characters in block scalar' => [
                <<<'EOT'
data: !!binary |
    SGVsbG8gd29yb===
EOT
                ,
                '/The base64 encoded data \(.*\) contains invalid characters/',
            ],
            'misplaced equals character in block scalar' => [
                <<<'EOT'
data: !!binary |
    SGVsbG8gd29ybG=Q
EOT
                ,
                '/The base64 encoded data \(.*\) contains invalid characters/',
            ],
        ];
    }

    public function testParseDateWithSubseconds()
    {
        $yaml = <<<'EOT'
date: 2002-12-14T01:23:45.670000Z
EOT;

        $this->assertSameData(['date' => 1039829025.67], $this->parser->parse($yaml));
    }

    public function testParseDateAsMappingValue()
    {
        $yaml = <<<'EOT'
date: 2002-12-14
EOT;
        $expectedDate = (new \DateTimeImmutable())
            ->setTimeZone(new \DateTimeZone('UTC'))
            ->setDate(2002, 12, 14)
            ->setTime(0, 0, 0);

        $this->assertSameData(['date' => $expectedDate], $this->parser->parse($yaml, Yaml::PARSE_DATETIME));
    }

    /**
     * @dataProvider parserThrowsExceptionWithCorrectLineNumberProvider
     */
    public function testParserThrowsExceptionWithCorrectLineNumber($lineNumber, $yaml)
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage(\sprintf('Unexpected characters near "," at line %d (near "bar: "123",").', $lineNumber));

        $this->parser->parse($yaml);
    }

    public static function parserThrowsExceptionWithCorrectLineNumberProvider()
    {
        return [
            [
                4,
                <<<'YAML'
foo:
    -
        # bar
        bar: "123",
YAML,
            ],
            [
                5,
                <<<'YAML'
foo:
    -
        # bar
        # bar
        bar: "123",
YAML,
            ],
            [
                8,
                <<<'YAML'
foo:
    -
        # foobar
        baz: 123
bar:
    -
        # bar
        bar: "123",
YAML,
            ],
            [
                10,
                <<<'YAML'
foo:
    -
        # foobar
        # foobar
        baz: 123
bar:
    -
        # bar
        # bar
        bar: "123",
YAML,
            ],
        ];
    }

    public function testParseMultiLineQuotedString()
    {
        $yaml = <<<EOT
foo: "bar
  baz
   foobar
foo"
bar: baz
EOT;

        $this->assertSame(['foo' => 'bar baz foobar foo', 'bar' => 'baz'], $this->parser->parse($yaml));
    }

    public function testMultiLineQuotedStringWithTrailingBackslash()
    {
        $yaml = <<<YAML
foobar:
    "foo\
    bar"
YAML;

        $this->assertSame(['foobar' => 'foobar'], $this->parser->parse($yaml));
    }

    public function testCommentCharactersInMultiLineQuotedStrings()
    {
        $yaml = <<<YAML
foo:
    foobar: 'foo
      #bar'
    bar: baz
YAML;
        $expected = [
            'foo' => [
                'foobar' => 'foo #bar',
                'bar' => 'baz',
            ],
        ];

        $this->assertSame($expected, $this->parser->parse($yaml));
    }

    public function testBlankLinesInQuotedMultiLineString()
    {
        $yaml = <<<YAML
foobar: 'foo

    bar'
YAML;
        $expected = [
            'foobar' => "foo\nbar",
        ];

        $this->assertSame($expected, $this->parser->parse($yaml));
    }

    public function testEscapedQuoteInQuotedMultiLineString()
    {
        $yaml = <<<YAML
foobar: "foo
    \\"bar\\"
    baz"
YAML;
        $expected = [
            'foobar' => 'foo "bar" baz',
        ];

        $this->assertSame($expected, $this->parser->parse($yaml));
    }

    public function testBackslashInQuotedMultiLineString()
    {
        $yaml = <<<YAML
foobar: "foo
    bar\\\\"
YAML;
        $expected = [
            'foobar' => 'foo bar\\',
        ];

        $this->assertSame($expected, $this->parser->parse($yaml));
    }

    /**
     * @dataProvider wrappedUnquotedStringsProvider
     */
    public function testWrappedUnquotedStringWithMultipleSpacesInValue(string $yaml, array $expected)
    {
        $this->assertSame($expected, $this->parser->parse($yaml));
    }

    public static function wrappedUnquotedStringsProvider()
    {
        return [
            'mapping' => [
                '{ foo: bar  bar, fiz: cat      cat }',
                [
                    'foo' => 'bar  bar',
                    'fiz' => 'cat      cat',
                ],
            ],
            'sequence' => [
                '[ bar  bar, cat      cat ]',
                [
                    'bar  bar',
                    'cat      cat',
                ],
            ],
        ];
    }

    public function testParseMultiLineUnquotedString()
    {
        $yaml = <<<EOT
foo: bar
  baz
   foobar
  foo
bar: baz
EOT;

        $this->assertSame(['foo' => 'bar baz foobar foo', 'bar' => 'baz'], $this->parser->parse($yaml));
    }

    /**
     * @dataProvider unquotedStringWithTrailingComment
     */
    public function testParseMultiLineUnquotedStringWithTrailingComment(string $yaml, array $expected)
    {
        $this->assertSame($expected, $this->parser->parse($yaml));
    }

    public static function unquotedStringWithTrailingComment()
    {
        return [
            'comment after comma' => [
                <<<'YAML'
                {
                    foo: 3, # comment
                    bar: 3
                }
                YAML,
                ['foo' => 3, 'bar' => 3],
            ],
            'comment after space' => [
                <<<'YAML'
                {
                    foo: 3 # comment
                }
                YAML,
                ['foo' => 3],
            ],
            'comment after space, but missing space after #' => [
                <<<'YAML'
                {
                    foo: 3 #comment
                }
                YAML,
                ['foo' => 3],
            ],
            'comment after tab' => [
                <<<YAML
                {
                    foo: 3\t# comment
                }
                YAML,
                ['foo' => 3],
            ],
            'comment after tab, but missing space after #' => [
                <<<YAML
                {
                    foo: 3\t#comment
                }
                YAML,
                ['foo' => 3],
            ],
            '# in mapping value' => [
                <<<'YAML'
                {
                    foo: example.com/#about
                }
                YAML,
                ['foo' => 'example.com/#about'],
            ],
        ];
    }

    /**
     * @dataProvider escapedQuotationCharactersInQuotedStrings
     */
    public function testParseQuotedStringContainingEscapedQuotationCharacters(string $yaml, array $expected)
    {
        $this->assertSame($expected, $this->parser->parse($yaml));
    }

    public static function escapedQuotationCharactersInQuotedStrings()
    {
        return [
            'single quoted string' => [
                <<<YAML
entries:
 - message: 'No emails received before timeout - Address: ''test@testemail.company.com''
       Keyword: ''Your Order confirmation'' ttl: 50'
   outcome: failed
YAML
                ,
                [
                    'entries' => [
                        [
                            'message' => 'No emails received before timeout - Address: \'test@testemail.company.com\' Keyword: \'Your Order confirmation\' ttl: 50',
                            'outcome' => 'failed',
                        ],
                    ],
                ],
            ],
            'double quoted string' => [
                <<<YAML
entries:
 - message: "No emails received before timeout - Address: \"test@testemail.company.com\"
       Keyword: \"Your Order confirmation\" ttl: 50"
   outcome: failed
YAML
                ,
                [
                    'entries' => [
                        [
                            'message' => 'No emails received before timeout - Address: "test@testemail.company.com" Keyword: "Your Order confirmation" ttl: 50',
                            'outcome' => 'failed',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testBackslashInSingleQuotedString()
    {
        $this->assertSame(['foo' => 'bar\\'], $this->parser->parse("foo: 'bar\'"));
    }

    public function testParseMultiLineString()
    {
        $this->assertSame("foo bar\nbaz", $this->parser->parse("foo\nbar\n\nbaz"));
    }

    /**
     * @dataProvider multiLineDataProvider
     */
    public function testParseMultiLineMappingValue($yaml, $expected, $parseError)
    {
        $this->assertSame($expected, $this->parser->parse($yaml));
    }

    public static function multiLineDataProvider()
    {
        $tests = [];

        $yaml = <<<'EOF'
foo:
- bar:
    one

    two
    three
EOF;
        $expected = [
            'foo' => [
                [
                    'bar' => "one\ntwo three",
                ],
            ],
        ];

        $tests[] = [$yaml, $expected, false];

        $yaml = <<<'EOF'
bar
"foo"
EOF;
        $expected = 'bar "foo"';

        $tests[] = [$yaml, $expected, false];

        $yaml = <<<'EOF'
bar
"foo
EOF;
        $expected = 'bar "foo';

        $tests[] = [$yaml, $expected, false];

        $yaml = <<<'EOF'
bar

'foo'
EOF;
        $expected = "bar\n'foo'";

        $tests[] = [$yaml, $expected, false];

        $yaml = <<<'EOF'
bar

foo'
EOF;
        $expected = "bar\nfoo'";

        $tests[] = [$yaml, $expected, false];

        return $tests;
    }

    /**
     * @dataProvider inlineNotationSpanningMultipleLinesProvider
     */
    public function testInlineNotationSpanningMultipleLines($expected, string $yaml)
    {
        $this->assertSame($expected, $this->parser->parse($yaml));
    }

    public static function inlineNotationSpanningMultipleLinesProvider(): array
    {
        return [
            'mapping' => [
                ['foo' => 'bar', 'bar' => 'baz'],
                <<<YAML
{
    'foo': 'bar',
    'bar': 'baz'
}
YAML
                ,
            ],
            'mapping with unquoted strings and values' => [
                ['foo' => 'bar', 'bar' => 'baz'],
                <<<YAML
{
    foo: bar,
    bar: baz
}
YAML
                ,
            ],
            'sequence' => [
                ['foo', 'bar'],
                <<<YAML
[
    'foo',
    'bar'
]
YAML
                ,
            ],
            'sequence with unquoted items' => [
                ['foo', 'bar'],
                <<<YAML
[
    foo,
    bar
]
YAML
                ,
            ],
            'nested mapping terminating at end of line' => [
                [
                    'foo' => [
                        'bar' => 'foobar',
                    ],
                ],
                <<<YAML
{ foo: { bar: foobar }
}
YAML
                ,
            ],
            'nested sequence terminating at end of line' => [
                [
                    'foo',
                    [
                        'bar',
                        'baz',
                    ],
                ],
                <<<YAML
[ foo, [bar, baz]
]
YAML,
            ],
            'nested sequence spanning multiple lines' => [
                [
                    ['entry1', []],
                    ['entry2'],
                ],
                <<<YAML
[
    ['entry1', {}],
    ['entry2']
]
YAML,
            ],
            'sequence nested in mapping' => [
                ['foo' => ['bar', 'foobar'], 'bar' => ['baz']],
                <<<YAML
{
    'foo': ['bar', 'foobar'],
    'bar': ['baz']
}
YAML
                ,
            ],
            'sequence spanning multiple lines nested in mapping' => [
                [
                    'foobar' => [
                        'foo',
                        'bar',
                        'baz',
                    ],
                ],
                <<<YAML
foobar: [foo,
    bar,
    baz
]
YAML
                ,
            ],
            'sequence spanning multiple lines nested in mapping with a following mapping' => [
                [
                    'foobar' => [
                        'foo',
                        'bar',
                    ],
                    'bar' => 'baz',
                ],
                <<<YAML
foobar: [
    foo,
    bar,
]
bar: baz
YAML,
            ],
            'nested sequence nested in mapping starting on the same line' => [
                [
                    'foo' => [
                        'foobar',
                        [
                            'bar',
                            'baz',
                        ],
                    ],
                ],
                <<<YAML
foo: [foobar, [
    bar,
    baz
]]
YAML
                ,
            ],
            'nested sequence nested in mapping starting on the following line' => [
                [
                    'foo' => [
                        'foobar',
                        [
                            'bar',
                            'baz',
                        ],
                    ],
                ],
                <<<YAML
foo: [foobar,
    [
        bar,
        baz
]]
YAML
                ,
            ],
            'mapping nested in sequence' => [
                ['foo', ['bar' => 'baz']],
                <<<YAML
[
    'foo',
    {
        'bar': 'baz'
    }
]
YAML
                ,
            ],
            'mapping spanning multiple lines nested in sequence' => [
                [
                    [
                        'foo' => 'bar',
                        'bar' => 'baz',
                    ],
                ],
                <<<YAML
- {
    foo: bar,
    bar: baz
}
YAML
                ,
            ],
            'nested mapping nested in sequence starting on the same line' => [
                [
                    [
                        'foo' => [
                            'bar' => 'foobar',
                        ],
                        'bar' => 'baz',
                    ],
                ],
                <<<YAML
- { foo: {
        bar: foobar
    },
    bar: baz
}
YAML
                ,
            ],
            'nested mapping nested in sequence starting on the following line' => [
                [
                    [
                        'foo' => [
                            'bar' => 'foobar',
                        ],
                        'bar' => 'baz',
                    ],
                ],
                <<<YAML
- { foo:
    {
        bar: foobar
    },
    bar: baz
}
YAML
                ,
            ],
            'single quoted multi-line string' => [
                "foo\nbar",
                <<<YAML
'foo

bar'
YAML
                ,
            ],
            'double quoted multi-line string' => [
                "foo\nbar",
                <<<YAML
'foo

bar'
YAML
                ,
            ],
            'single-quoted multi-line mapping value' => [
                ['foo' => "bar\nbaz"],
                <<<YAML
foo: 'bar

baz'
YAML,
            ],
            'mixed mapping with inline notation having separated lines' => [
                [
                    'map' => [
                        'key' => 'value',
                        'a' => 'b',
                    ],
                    'param' => 'some',
                ],
                <<<YAML
map: {
    key: "value",
    a: "b"
}
param: "some"
YAML,
            ],
            'mixed mapping with inline notation on one line' => [
                [
                    'map' => [
                        'key' => 'value',
                        'a' => 'b',
                    ],
                    'param' => 'some',
                ],
                <<<YAML
map: {key: "value", a: "b"}
param: "some"
YAML,
            ],
            'mixed mapping with inline notation on one line with a comment' => [
                [
                    'map' => [
                        'key' => 'value',
                        'a' => 'b',
                    ],
                    'param' => 'some',
                ],
                <<<YAML
map: {key: "value", a: "b"} # comment
param: "some"
YAML,
            ],
            'mixed mapping with compact inline notation on one line' => [
                [
                    'map' => [
                        'key' => 'value',
                        'a' => 'b',
                    ],
                    'param' => 'some',
                ],
                <<<YAML
map: {key: "value",
a: "b"}
param: "some"
YAML,
            ],
            'nested collections containing strings with bracket chars' => [
                [
                    [']'],
                    ['}'],
                    ['ba[r'],
                    ['[ba]r'],
                    ['bar]'],
                    ['foo' => 'bar{'],
                    ['foo' => 'b{ar}'],
                    ['foo' => 'bar}'],
                ],
                <<<YAML
[
    [
        "]"
    ],
    [
        "}"
    ],
    [
        "ba[r"
    ],
    [
        '[ba]r'
    ],
    [
        "bar]"
    ],
    {
        foo: "bar{"
    },
    {
        foo: "b{ar}"
    },
    {
        foo: 'bar}'
    }
]
YAML,
            ],
            'escaped characters in quoted strings' => [
                [
                    ['te"st'],
                    ['test'],
                    ["te'st"],
                    ['te"st]'],
                    ['te"st'],
                    ['test'],
                    ["te'st"],
                    ['te"st]'],
                ],
                <<<YAML
[
    ["te\"st"],["test"],['te''st'],["te\"st]"],
    ["te\"st"],
    ["test"],
    ['te''st'],
    ["te\"st]"]
]
YAML,
            ],
        ];
    }

    public function testRootLevelInlineMappingFollowedByMoreContentIsInvalid()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Unable to parse at line 2 (near "foobar").');

        $yaml = <<<YAML
{ foo: bar }
foobar
YAML;

        $this->parser->parse($yaml);
    }

    public function testInlineMappingFollowedByMoreContentIsInvalid()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Unexpected token "baz" at line 1 (near "{ foo: bar } baz").');

        $yaml = <<<YAML
{ foo: bar } baz
YAML;

        $this->parser->parse($yaml);
    }

    public function testInlineSequenceFollowedByMoreContentIsInvalid()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Unexpected token ",bar," at line 1 (near "[\'foo\'],bar,").');

        $yaml = <<<YAML
['foo'],bar,
YAML;

        $this->parser->parse($yaml);
    }

    public function testTaggedInlineMapping()
    {
        $this->assertSameData(new TaggedValue('foo', ['foo' => 'bar']), $this->parser->parse('!foo {foo: bar}', Yaml::PARSE_CUSTOM_TAGS));
    }

    public function testInvalidInlineSequenceContainingStringWithEscapedQuotationCharacter()
    {
        $this->expectException(ParseException::class);

        $this->parser->parse('["\\"]');
    }

    /**
     * @dataProvider taggedValuesProvider
     */
    public function testCustomTagSupport($expected, $yaml)
    {
        $this->assertSameData($expected, $this->parser->parse($yaml, Yaml::PARSE_CUSTOM_TAGS));
    }

    public static function taggedValuesProvider()
    {
        return [
            'scalars' => [
                [
                    'foo' => new TaggedValue('inline', 'bar'),
                    'quz' => new TaggedValue('long', 'this is a long text'),
                ],
                <<<YAML
foo: !inline bar
quz: !long >
  this is a long
  text
YAML,
            ],
            'sequences' => [
                [new TaggedValue('foo', ['yaml']), new TaggedValue('quz', ['bar'])],
                <<<YAML
- !foo
    - yaml
- !quz [bar]
YAML,
            ],
            'mappings' => [
                new TaggedValue('foo', ['foo' => new TaggedValue('quz', ['bar']), 'quz' => new TaggedValue('foo', ['quz' => 'bar'])]),
                <<<YAML
!foo
foo: !quz [bar]
quz: !foo
   quz: bar
YAML,
            ],
            'inline' => [
                [new TaggedValue('foo', ['foo', 'bar']), new TaggedValue('quz', ['foo' => 'bar', 'quz' => new TaggedValue('bar', ['one' => 'bar'])])],
                <<<YAML
- !foo [foo, bar]
- !quz {foo: bar, quz: !bar {one: bar}}
YAML,
            ],
            'spaces-around-tag-value-in-sequence' => [
                [new TaggedValue('foo', 'bar')],
                '[ !foo bar ]',
            ],
            'with-comments' => [
                [
                    [new TaggedValue('foo', ['foo', 'baz'])],
                ],
                <<<YAML
- [!foo [
    foo,
    baz
    #bar
  ]]
YAML,
            ],
            'with-comments-trailing-comma' => [
                [
                    [new TaggedValue('foo', ['foo', 'baz'])],
                ],
                <<<YAML
- [!foo [
    foo,
    baz,
    #bar
  ]]
YAML,
            ],
        ];
    }

    public function testNonSpecificTagSupport()
    {
        $this->assertSame(12, $this->parser->parse('! 12'));
    }

    public function testCustomTagsDisabled()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Tags support is not enabled. Enable the "Yaml::PARSE_CUSTOM_TAGS" flag to use "!iterator" at line 1 (near "!iterator [foo]").');
        $this->parser->parse('!iterator [foo]');
    }

    public function testUnsupportedTagWithScalar()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Tags support is not enabled. Enable the "Yaml::PARSE_CUSTOM_TAGS" flag to use "!iterator" at line 1 (near "!iterator foo").');
        $this->parser->parse('!iterator foo');
    }

    public function testUnsupportedBuiltInTagWithScalar()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('The string "!!iterator foo" could not be parsed as it uses an unsupported built-in tag at line 1 (near "!!iterator foo").');
        $this->parser->parse('!!iterator foo');
    }

    public function testExceptionWhenUsingUnsupportedBuiltInTags()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('The built-in tag "!!foo" is not implemented at line 1 (near "!!foo").');
        $this->parser->parse('!!foo');
    }

    public function testComplexMappingThrowsParseException()
    {
        $yaml = <<<YAML
? "1"
:
  name: végétalien
YAML;

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Complex mappings are not supported at line 1 (near "? "1"").');

        $this->parser->parse($yaml);
    }

    public function testComplexMappingNestedInMappingThrowsParseException()
    {
        $yaml = <<<YAML
diet:
  ? "1"
  :
    name: végétalien
YAML;

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Complex mappings are not supported at line 2 (near "? "1"").');

        $this->parser->parse($yaml);
    }

    public function testComplexMappingNestedInSequenceThrowsParseException()
    {
        $yaml = <<<YAML
- ? "1"
  :
    name: végétalien
YAML;

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Complex mappings are not supported at line 1 (near "- ? "1"").');

        $this->parser->parse($yaml);
    }

    public function testParsingIniThrowsException()
    {
        $ini = <<<INI
[parameters]
  foo = bar
  bar = %foo%
INI;

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Unable to parse at line 2 (near "  foo = bar").');

        $this->parser->parse($ini);
    }

    private static function loadTestsFromFixtureFiles($testsFile)
    {
        $parser = new Parser();

        $tests = [];
        $files = $parser->parseFile(__DIR__.'/Fixtures/'.$testsFile);
        foreach ($files as $file) {
            $yamls = file_get_contents(__DIR__.'/Fixtures/'.$file.'.yml');

            // split YAMLs documents
            foreach (preg_split('/^---( %YAML\:1\.0)?/m', $yamls) as $yaml) {
                if (!$yaml) {
                    continue;
                }

                $test = $parser->parse($yaml);
                if (isset($test['todo']) && $test['todo']) {
                    // TODO
                } else {
                    eval('$expected = '.trim($test['php']).';');

                    $tests[] = [var_export($expected, true), $test['yaml'], $test['test']];
                }
            }
        }

        return $tests;
    }

    public function testCanParseVeryLongValue()
    {
        $longStringWithSpaces = str_repeat('xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx ', 20000);
        $trickyVal = ['x' => $longStringWithSpaces];

        $yamlString = Yaml::dump($trickyVal);
        $arrayFromYaml = $this->parser->parse($yamlString);

        $this->assertSame($trickyVal, $arrayFromYaml);
    }

    public function testParserCleansUpReferencesBetweenRuns()
    {
        $yaml = <<<YAML
foo: &foo
    baz: foobar
bar:
    <<: *foo
YAML;
        $this->parser->parse($yaml);

        $yaml = <<<YAML
bar:
    <<: *foo
YAML;

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Reference "foo" does not exist at line 2');

        $this->parser->parse($yaml);
    }

    public function testPhpConstantTagMappingKey()
    {
        $yaml = <<<YAML
transitions:
    !php/const 'Symfony\Component\Yaml\Tests\B::FOO':
        from:
            - !php/const 'Symfony\Component\Yaml\Tests\B::BAR'
        to: !php/const 'Symfony\Component\Yaml\Tests\B::BAZ'
YAML;
        $expected = [
            'transitions' => [
                'foo' => [
                    'from' => [
                        'bar',
                    ],
                    'to' => 'baz',
                ],
            ],
        ];

        $this->assertSame($expected, $this->parser->parse($yaml, Yaml::PARSE_CONSTANT));
    }

    public function testWrongPhpConstantSyntax()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Missing value for tag "php/const:App\Kernel::SEMART_VERSION" at line 1 (near "!php/const:App\Kernel::SEMART_VERSION").');

        $this->parser->parse('!php/const:App\Kernel::SEMART_VERSION', Yaml::PARSE_CUSTOM_TAGS | Yaml::PARSE_CONSTANT);
    }

    public function testPhpConstantTagMappingAsScalarKey()
    {
        $yaml = <<<YAML
map1:
  - foo: 'value_0'
    !php/const 'Symfony\Component\Yaml\Tests\B::BAR': 'value_1'
map2:
  - !php/const 'Symfony\Component\Yaml\Tests\B::FOO': 'value_0'
    bar: 'value_1'
YAML;
        $this->assertSame([
            'map1' => [['foo' => 'value_0', 'bar' => 'value_1']],
            'map2' => [['foo' => 'value_0', 'bar' => 'value_1']],
        ], $this->parser->parse($yaml, Yaml::PARSE_CONSTANT));
    }

    public function testTagMappingAsScalarKey()
    {
        $yaml = <<<YAML
map1:
  - !!str 0: 'value_0'
    !!str 1: 'value_1'
YAML;
        $this->assertSame([
            'map1' => [['0' => 'value_0', '1' => 'value_1']],
        ], $this->parser->parse($yaml));
    }

    public function testMergeKeysWhenMappingsAreParsedAsObjects()
    {
        $yaml = <<<YAML
foo: &FOO
    bar: 1
bar: &BAR
    baz: 2
    <<: *FOO
baz:
    baz_foo: 3
    <<:
        baz_bar: 4
foobar:
    bar: ~
    <<: [*FOO, *BAR]
YAML;
        $expected = (object) [
            'foo' => (object) [
                'bar' => 1,
            ],
            'bar' => (object) [
                'baz' => 2,
                'bar' => 1,
            ],
            'baz' => (object) [
                'baz_foo' => 3,
                'baz_bar' => 4,
            ],
            'foobar' => (object) [
                'bar' => null,
                'baz' => 2,
            ],
        ];

        $this->assertSameData($expected, $this->parser->parse($yaml, Yaml::PARSE_OBJECT_FOR_MAP));
    }

    public function testFilenamesAreParsedAsStringsWithoutFlag()
    {
        $file = __DIR__.'/Fixtures/index.yml';

        $this->assertSame($file, $this->parser->parse($file));
    }

    public function testParseFile()
    {
        $this->assertIsArray($this->parser->parseFile(__DIR__.'/Fixtures/index.yml'));
    }

    public function testParsingNonExistentFilesThrowsException()
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessageMatches('#^File ".+/Fixtures/nonexistent.yml" does not exist\.$#');
        $this->parser->parseFile(__DIR__.'/Fixtures/nonexistent.yml');
    }

    public function testParsingNotReadableFilesThrowsException()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('chmod is not supported on Windows');
        }

        if (!getenv('USER') || 'root' === getenv('USER')) {
            $this->markTestSkipped('This test will fail if run under superuser');
        }

        $file = __DIR__.'/Fixtures/not_readable.yml';
        chmod($file, 0200);

        $this->expectException(ParseException::class);
        $this->expectExceptionMessageMatches('#^File ".+/Fixtures/not_readable.yml" cannot be read\.$#');

        $this->parser->parseFile($file);
    }

    public function testParseReferencesOnMergeKeys()
    {
        $yaml = <<<YAML
mergekeyrefdef:
    a: foo
    <<: &quux
        b: bar
        c: baz
mergekeyderef:
    d: quux
    <<: *quux
YAML;
        $expected = [
            'mergekeyrefdef' => [
                'a' => 'foo',
                'b' => 'bar',
                'c' => 'baz',
            ],
            'mergekeyderef' => [
                'd' => 'quux',
                'b' => 'bar',
                'c' => 'baz',
            ],
        ];

        $this->assertSame($expected, $this->parser->parse($yaml));
    }

    public function testParseReferencesOnMergeKeysWithMappingsParsedAsObjects()
    {
        $yaml = <<<YAML
mergekeyrefdef:
    a: foo
    <<: &quux
        b: bar
        c: baz
mergekeyderef:
    d: quux
    <<: *quux
YAML;
        $expected = (object) [
            'mergekeyrefdef' => (object) [
                'a' => 'foo',
                'b' => 'bar',
                'c' => 'baz',
            ],
            'mergekeyderef' => (object) [
                'd' => 'quux',
                'b' => 'bar',
                'c' => 'baz',
            ],
        ];

        $this->assertSameData($expected, $this->parser->parse($yaml, Yaml::PARSE_OBJECT_FOR_MAP));
    }

    public function testEvalRefException()
    {
        $yaml = <<<EOE
foo: { &foo { a: Steve, <<: *foo} }
EOE;

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Reference "foo" does not exist');

        $this->parser->parse($yaml);
    }

    /**
     * @dataProvider circularReferenceProvider
     */
    public function testDetectCircularReferences($yaml)
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Circular reference [foo, bar, foo] detected');
        $this->parser->parse($yaml, Yaml::PARSE_CUSTOM_TAGS);
    }

    public static function circularReferenceProvider()
    {
        $tests = [];

        $yaml = <<<YAML
foo:
    - &foo
      - &bar
        bar: foobar
        baz: *foo
YAML;
        $tests['sequence'] = [$yaml];

        $yaml = <<<YAML
foo: &foo
    bar: &bar
        foobar: baz
        baz: *foo
YAML;
        $tests['mapping'] = [$yaml];

        $yaml = <<<YAML
foo: &foo
    bar: &bar
        foobar: baz
        <<: *foo
YAML;
        $tests['mapping with merge key'] = [$yaml];

        return $tests;
    }

    public function testBlockScalarArray()
    {
        $yaml = <<<'YAML'
anyOf:
  - $ref: >-
      #/string/bar
anyOfMultiline:
  - $ref: >-
      #/string/bar
      second line
nested:
  anyOf:
    - $ref: >-
        #/string/bar
YAML;
        $expected = [
            'anyOf' => [
                0 => [
                    '$ref' => '#/string/bar',
                ],
            ],
            'anyOfMultiline' => [
                0 => [
                    '$ref' => '#/string/bar second line',
                ],
            ],
            'nested' => [
                'anyOf' => [
                    0 => [
                        '$ref' => '#/string/bar',
                    ],
                ],
            ],
        ];

        $this->assertSame($expected, $this->parser->parse($yaml));
    }

    /**
     * @dataProvider indentedMappingData
     */
    public function testParseIndentedMappings($yaml, $expected)
    {
        $this->assertSame($expected, $this->parser->parse($yaml));
    }

    public static function indentedMappingData()
    {
        $tests = [];

        $yaml = <<<YAML
foo:
  - bar: "foobar"
    # A comment
    baz: "foobaz"
YAML;
        $expected = [
            'foo' => [
                [
                    'bar' => 'foobar',
                    'baz' => 'foobaz',
                ],
            ],
        ];
        $tests['comment line is first line in indented block'] = [$yaml, $expected];

        $yaml = <<<YAML
foo:
    - bar:
        # comment
        baz: [1, 2, 3]
YAML;
        $expected = [
            'foo' => [
                [
                    'bar' => [
                        'baz' => [1, 2, 3],
                    ],
                ],
            ],
        ];
        $tests['mapping value on new line starting with a comment line'] = [$yaml, $expected];

        $yaml = <<<YAML
foo:
  -
    bar: foobar
YAML;
        $expected = [
            'foo' => [
                [
                    'bar' => 'foobar',
                ],
            ],
        ];
        $tests['mapping in sequence starting on a new line'] = [$yaml, $expected];

        $yaml = <<<YAML
foo:

    bar: baz
YAML;
        $expected = [
            'foo' => [
                'bar' => 'baz',
            ],
        ];
        $tests['blank line at the beginning of an indented mapping value'] = [$yaml, $expected];

        return $tests;
    }

    public function testMultiLineComment()
    {
        $yaml = <<<YAML
parameters:
    abc

# Comment
YAML;

        $this->assertSame(['parameters' => 'abc'], $this->parser->parse($yaml));
    }

    public function testParseValueWithModifiers()
    {
        $yaml = <<<YAML
parameters:
    abc: |+5 # plus five spaces indent
         one
         two
         three
         four
         five
YAML;
        $this->assertSame(
            [
                'parameters' => [
                    'abc' => implode("\n", ['one', 'two', 'three', 'four', 'five']),
                ],
            ],
            $this->parser->parse($yaml)
        );
    }

    public function testParseValueWithNegativeModifiers()
    {
        $yaml = <<<YAML
parameters:
    abc: |-3 # minus
       one
       two
       three
       four
       five
YAML;
        $this->assertSame(
            [
                'parameters' => [
                    'abc' => implode("\n", ['one', 'two', 'three', 'four', 'five']),
                ],
            ],
            $this->parser->parse($yaml)
        );
    }

    public function testThrowExceptionIfInvalidAdditionalClosingTagOccurs()
    {
        $yaml = '{
            "object": {
                    "array": [
                        "a",
                        "b",
                        "c"
                    ]
                ],
            }
        }';

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Malformed unquoted YAML string at line 8 (near "                ],").');

        $this->parser->parse($yaml);
    }

    public function testWhitespaceAtEndOfLine()
    {
        $yaml = "\nfoo:\n    arguments: [ '@bar' ]  \n";
        $this->assertSame(
            [
                'foo' => [
                    'arguments' => ['@bar'],
                ],
            ],
            $this->parser->parse($yaml)
        );

        $yaml = "\nfoo:\n    bar: {} \n";
        $this->assertSame(
            [
                'foo' => [
                    'bar' => [],
                ],
            ],
            $this->parser->parse($yaml)
        );

        $this->assertSame(
            [
                'foo' => 'bar',
                'foobar' => 'baz',
            ],
            $this->parser->parse("foo: 'bar' \nfoobar: baz")
        );
    }

    /**
     * This is a regression test for a bug where a YAML block with a nested multiline string using | was parsed without
     * a trailing \n when a shorter YAML document was parsed before.
     *
     * When a shorter document was parsed before, the nested string did not have a \n at the end of the string, because
     * the Parser thought it was the end of the file, even though it is not.
     */
    public function testParsingMultipleDocuments()
    {
        $shortDocument = 'foo: bar';
        $longDocument = <<<YAML
a:
    b: |
        row
        row2
c: d
YAML;

        // The first parsing set and fixed the totalNumberOfLines in the Parser before, so parsing the short document here
        // to reproduce the issue. If the issue would not have been fixed, the next assertion will fail
        $this->parser->parse($shortDocument);

        // After the total number of lines has been reset the result will be the same as if a new parser was used
        // (before, there was no \n after row2)
        $this->assertSame(['a' => ['b' => "row\nrow2\n"], 'c' => 'd'], $this->parser->parse($longDocument));
    }

    public function testParseIdeographicSpaces()
    {
        $expected = <<<YAML
unquoted: \u{3000}
quoted: '\u{3000}'
within_string: 'a　b'
regular_space: 'a b'
YAML;
        $this->assertSame([
            'unquoted' => '　',
            'quoted' => '　',
            'within_string' => 'a　b',
            'regular_space' => 'a b',
        ], $this->parser->parse($expected));
    }

    public function testSkipBlankLines()
    {
        $this->assertSame(['foo' => [null]], (new Parser())->parse("foo:\n-\n\n"));
    }

    private function assertSameData($expected, $actual)
    {
        $this->assertEquals($expected, $actual);
        $this->assertSame(
            var_export($expected, true),
            var_export($actual, true)
        );
    }
}

class B
{
    public $b = 'foo';

    public const FOO = 'foo';
    public const BAR = 'bar';
    public const BAZ = 'baz';
}
