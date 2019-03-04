<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Tests\Parser;

use PHPUnit\Framework\TestCase;
use Symfony\Component\CssSelector\Exception\SyntaxErrorException;
use Symfony\Component\CssSelector\Node\FunctionNode;
use Symfony\Component\CssSelector\Node\SelectorNode;
use Symfony\Component\CssSelector\Parser\Parser;
use Symfony\Component\CssSelector\Parser\Token;

class ParserTest extends TestCase
{
    /** @dataProvider getParserTestData */
    public function testParser($source, $representation)
    {
        $parser = new Parser();

        $this->assertEquals($representation, array_map(function (SelectorNode $node) {
            return (string) $node->getTree();
        }, $parser->parse($source)));
    }

    /** @dataProvider getParserExceptionTestData */
    public function testParserException($source, $message)
    {
        $parser = new Parser();

        try {
            $parser->parse($source);
            $this->fail('Parser should throw a SyntaxErrorException.');
        } catch (SyntaxErrorException $e) {
            $this->assertEquals($message, $e->getMessage());
        }
    }

    /** @dataProvider getPseudoElementsTestData */
    public function testPseudoElements($source, $element, $pseudo)
    {
        $parser = new Parser();
        $selectors = $parser->parse($source);
        $this->assertCount(1, $selectors);

        /** @var SelectorNode $selector */
        $selector = $selectors[0];
        $this->assertEquals($element, (string) $selector->getTree());
        $this->assertEquals($pseudo, (string) $selector->getPseudoElement());
    }

    /** @dataProvider getSpecificityTestData */
    public function testSpecificity($source, $value)
    {
        $parser = new Parser();
        $selectors = $parser->parse($source);
        $this->assertCount(1, $selectors);

        /** @var SelectorNode $selector */
        $selector = $selectors[0];
        $this->assertEquals($value, $selector->getSpecificity()->getValue());
    }

    /** @dataProvider getParseSeriesTestData */
    public function testParseSeries($series, $a, $b)
    {
        $parser = new Parser();
        $selectors = $parser->parse(sprintf(':nth-child(%s)', $series));
        $this->assertCount(1, $selectors);

        /** @var FunctionNode $function */
        $function = $selectors[0]->getTree();
        $this->assertEquals([$a, $b], Parser::parseSeries($function->getArguments()));
    }

    /** @dataProvider getParseSeriesExceptionTestData */
    public function testParseSeriesException($series)
    {
        $parser = new Parser();
        $selectors = $parser->parse(sprintf(':nth-child(%s)', $series));
        $this->assertCount(1, $selectors);

        /** @var FunctionNode $function */
        $function = $selectors[0]->getTree();
        $this->expectException('Symfony\Component\CssSelector\Exception\SyntaxErrorException');
        Parser::parseSeries($function->getArguments());
    }

    public function getParserTestData()
    {
        return [
            ['*', ['Element[*]']],
            ['*|*', ['Element[*]']],
            ['*|foo', ['Element[foo]']],
            ['foo|*', ['Element[foo|*]']],
            ['foo|bar', ['Element[foo|bar]']],
            ['#foo#bar', ['Hash[Hash[Element[*]#foo]#bar]']],
            ['div>.foo', ['CombinedSelector[Element[div] > Class[Element[*].foo]]']],
            ['div> .foo', ['CombinedSelector[Element[div] > Class[Element[*].foo]]']],
            ['div >.foo', ['CombinedSelector[Element[div] > Class[Element[*].foo]]']],
            ['div > .foo', ['CombinedSelector[Element[div] > Class[Element[*].foo]]']],
            ["div \n>  \t \t .foo", ['CombinedSelector[Element[div] > Class[Element[*].foo]]']],
            ['td.foo,.bar', ['Class[Element[td].foo]', 'Class[Element[*].bar]']],
            ['td.foo, .bar', ['Class[Element[td].foo]', 'Class[Element[*].bar]']],
            ["td.foo\t\r\n\f ,\t\r\n\f .bar", ['Class[Element[td].foo]', 'Class[Element[*].bar]']],
            ['td.foo,.bar', ['Class[Element[td].foo]', 'Class[Element[*].bar]']],
            ['td.foo, .bar', ['Class[Element[td].foo]', 'Class[Element[*].bar]']],
            ["td.foo\t\r\n\f ,\t\r\n\f .bar", ['Class[Element[td].foo]', 'Class[Element[*].bar]']],
            ['div, td.foo, div.bar span', ['Element[div]', 'Class[Element[td].foo]', 'CombinedSelector[Class[Element[div].bar] <followed> Element[span]]']],
            ['div > p', ['CombinedSelector[Element[div] > Element[p]]']],
            ['td:first', ['Pseudo[Element[td]:first]']],
            ['td :first', ['CombinedSelector[Element[td] <followed> Pseudo[Element[*]:first]]']],
            ['a[name]', ['Attribute[Element[a][name]]']],
            ["a[ name\t]", ['Attribute[Element[a][name]]']],
            ['a [name]', ['CombinedSelector[Element[a] <followed> Attribute[Element[*][name]]]']],
            ['[name="foo"]', ["Attribute[Element[*][name = 'foo']]"]],
            ["[name='foo[1]']", ["Attribute[Element[*][name = 'foo[1]']]"]],
            ["[name='foo[0][bar]']", ["Attribute[Element[*][name = 'foo[0][bar]']]"]],
            ['a[rel="include"]', ["Attribute[Element[a][rel = 'include']]"]],
            ['a[rel = include]', ["Attribute[Element[a][rel = 'include']]"]],
            ["a[hreflang |= 'en']", ["Attribute[Element[a][hreflang |= 'en']]"]],
            ['a[hreflang|=en]', ["Attribute[Element[a][hreflang |= 'en']]"]],
            ['div:nth-child(10)', ["Function[Element[div]:nth-child(['10'])]"]],
            [':nth-child(2n+2)', ["Function[Element[*]:nth-child(['2', 'n', '+2'])]"]],
            ['div:nth-of-type(10)', ["Function[Element[div]:nth-of-type(['10'])]"]],
            ['div div:nth-of-type(10) .aclass', ["CombinedSelector[CombinedSelector[Element[div] <followed> Function[Element[div]:nth-of-type(['10'])]] <followed> Class[Element[*].aclass]]"]],
            ['label:only', ['Pseudo[Element[label]:only]']],
            ['a:lang(fr)', ["Function[Element[a]:lang(['fr'])]"]],
            ['div:contains("foo")', ["Function[Element[div]:contains(['foo'])]"]],
            ['div#foobar', ['Hash[Element[div]#foobar]']],
            ['div:not(div.foo)', ['Negation[Element[div]:not(Class[Element[div].foo])]']],
            ['td ~ th', ['CombinedSelector[Element[td] ~ Element[th]]']],
            ['.foo[data-bar][data-baz=0]', ["Attribute[Attribute[Class[Element[*].foo][data-bar]][data-baz = '0']]"]],
        ];
    }

    public function getParserExceptionTestData()
    {
        return [
            ['attributes(href)/html/body/a', SyntaxErrorException::unexpectedToken('selector', new Token(Token::TYPE_DELIMITER, '(', 10))->getMessage()],
            ['attributes(href)', SyntaxErrorException::unexpectedToken('selector', new Token(Token::TYPE_DELIMITER, '(', 10))->getMessage()],
            ['html/body/a', SyntaxErrorException::unexpectedToken('selector', new Token(Token::TYPE_DELIMITER, '/', 4))->getMessage()],
            [' ', SyntaxErrorException::unexpectedToken('selector', new Token(Token::TYPE_FILE_END, '', 1))->getMessage()],
            ['div, ', SyntaxErrorException::unexpectedToken('selector', new Token(Token::TYPE_FILE_END, '', 5))->getMessage()],
            [' , div', SyntaxErrorException::unexpectedToken('selector', new Token(Token::TYPE_DELIMITER, ',', 1))->getMessage()],
            ['p, , div', SyntaxErrorException::unexpectedToken('selector', new Token(Token::TYPE_DELIMITER, ',', 3))->getMessage()],
            ['div > ', SyntaxErrorException::unexpectedToken('selector', new Token(Token::TYPE_FILE_END, '', 6))->getMessage()],
            ['  > div', SyntaxErrorException::unexpectedToken('selector', new Token(Token::TYPE_DELIMITER, '>', 2))->getMessage()],
            ['foo|#bar', SyntaxErrorException::unexpectedToken('identifier or "*"', new Token(Token::TYPE_HASH, 'bar', 4))->getMessage()],
            ['#.foo', SyntaxErrorException::unexpectedToken('selector', new Token(Token::TYPE_DELIMITER, '#', 0))->getMessage()],
            ['.#foo', SyntaxErrorException::unexpectedToken('identifier', new Token(Token::TYPE_HASH, 'foo', 1))->getMessage()],
            [':#foo', SyntaxErrorException::unexpectedToken('identifier', new Token(Token::TYPE_HASH, 'foo', 1))->getMessage()],
            ['[*]', SyntaxErrorException::unexpectedToken('"|"', new Token(Token::TYPE_DELIMITER, ']', 2))->getMessage()],
            ['[foo|]', SyntaxErrorException::unexpectedToken('identifier', new Token(Token::TYPE_DELIMITER, ']', 5))->getMessage()],
            ['[#]', SyntaxErrorException::unexpectedToken('identifier or "*"', new Token(Token::TYPE_DELIMITER, '#', 1))->getMessage()],
            ['[foo=#]', SyntaxErrorException::unexpectedToken('string or identifier', new Token(Token::TYPE_DELIMITER, '#', 5))->getMessage()],
            [':nth-child()', SyntaxErrorException::unexpectedToken('at least one argument', new Token(Token::TYPE_DELIMITER, ')', 11))->getMessage()],
            ['[href]a', SyntaxErrorException::unexpectedToken('selector', new Token(Token::TYPE_IDENTIFIER, 'a', 6))->getMessage()],
            ['[rel:stylesheet]', SyntaxErrorException::unexpectedToken('operator', new Token(Token::TYPE_DELIMITER, ':', 4))->getMessage()],
            ['[rel=stylesheet', SyntaxErrorException::unexpectedToken('"]"', new Token(Token::TYPE_FILE_END, '', 15))->getMessage()],
            [':lang(fr', SyntaxErrorException::unexpectedToken('an argument', new Token(Token::TYPE_FILE_END, '', 8))->getMessage()],
            [':contains("foo', SyntaxErrorException::unclosedString(10)->getMessage()],
            ['foo!', SyntaxErrorException::unexpectedToken('selector', new Token(Token::TYPE_DELIMITER, '!', 3))->getMessage()],
        ];
    }

    public function getPseudoElementsTestData()
    {
        return [
            ['foo', 'Element[foo]', ''],
            ['*', 'Element[*]', ''],
            [':empty', 'Pseudo[Element[*]:empty]', ''],
            [':BEfore', 'Element[*]', 'before'],
            [':aftER', 'Element[*]', 'after'],
            [':First-Line', 'Element[*]', 'first-line'],
            [':First-Letter', 'Element[*]', 'first-letter'],
            ['::befoRE', 'Element[*]', 'before'],
            ['::AFter', 'Element[*]', 'after'],
            ['::firsT-linE', 'Element[*]', 'first-line'],
            ['::firsT-letteR', 'Element[*]', 'first-letter'],
            ['::Selection', 'Element[*]', 'selection'],
            ['foo:after', 'Element[foo]', 'after'],
            ['foo::selection', 'Element[foo]', 'selection'],
            ['lorem#ipsum ~ a#b.c[href]:empty::selection', 'CombinedSelector[Hash[Element[lorem]#ipsum] ~ Pseudo[Attribute[Class[Hash[Element[a]#b].c][href]]:empty]]', 'selection'],
            ['video::-webkit-media-controls', 'Element[video]', '-webkit-media-controls'],
        ];
    }

    public function getSpecificityTestData()
    {
        return [
            ['*', 0],
            [' foo', 1],
            [':empty ', 10],
            [':before', 1],
            ['*:before', 1],
            [':nth-child(2)', 10],
            ['.bar', 10],
            ['[baz]', 10],
            ['[baz="4"]', 10],
            ['[baz^="4"]', 10],
            ['#lipsum', 100],
            [':not(*)', 0],
            [':not(foo)', 1],
            [':not(.foo)', 10],
            [':not([foo])', 10],
            [':not(:empty)', 10],
            [':not(#foo)', 100],
            ['foo:empty', 11],
            ['foo:before', 2],
            ['foo::before', 2],
            ['foo:empty::before', 12],
            ['#lorem + foo#ipsum:first-child > bar:first-line', 213],
        ];
    }

    public function getParseSeriesTestData()
    {
        return [
            ['1n+3', 1, 3],
            ['1n +3', 1, 3],
            ['1n + 3', 1, 3],
            ['1n+ 3', 1, 3],
            ['1n-3', 1, -3],
            ['1n -3', 1, -3],
            ['1n - 3', 1, -3],
            ['1n- 3', 1, -3],
            ['n-5', 1, -5],
            ['odd', 2, 1],
            ['even', 2, 0],
            ['3n', 3, 0],
            ['n', 1, 0],
            ['+n', 1, 0],
            ['-n', -1, 0],
            ['5', 0, 5],
        ];
    }

    public function getParseSeriesExceptionTestData()
    {
        return [
            ['foo'],
            ['n+'],
        ];
    }
}
