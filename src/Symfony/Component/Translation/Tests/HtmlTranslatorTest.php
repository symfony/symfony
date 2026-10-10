<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\DataCollectorTranslator;
use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Component\Translation\HtmlTranslator;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\LoggingTranslator;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\PseudoLocalizationTranslator;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

class HtmlTranslatorTest extends TestCase
{
    #[DataProvider('provideTrans')]
    public function testTrans(string $expected, string $id, array $parameters = [], array $tags = [])
    {
        $this->assertSame($expected, (new HtmlTranslator(new IdentityTranslator()))->trans($id, $parameters, null, null, $tags));
    }

    public static function provideTrans(): iterable
    {
        $stringable = new class {
            public function __toString(): string
            {
                return '<i>';
            }
        };

        yield 'tag' => ['Hello <em>Fabien</em>!', 'Hello <strong>%name%</strong>!', ['%name%' => 'Fabien'], ['strong' => ['tag' => 'em']]];
        yield 'tag with attributes' => ['Hello <span class="underline" data-id="42">Fabien</span>!', 'Hello <strong>%name%</strong>!', ['%name%' => 'Fabien'], ['strong' => ['tag' => 'span', 'attr' => ['class' => 'underline', 'data-id' => 42]]]];
        yield 'attribute values are escaped' => ['<a href="&quot; onclick=&quot;alert(1)&amp;">here</a>', '<link>here</link>', [], ['link' => ['tag' => 'a', 'attr' => ['href' => '" onclick="alert(1)&']]]];
        yield 'boolean and null attributes' => ['<button disabled>OK</button>', '<b>OK</b>', [], ['b' => ['tag' => 'button', 'attr' => ['disabled' => true, 'hidden' => false, 'title' => null]]]];
        yield 'float and stringable attribute values' => ['<span data-price="1.5" title="&lt;i&gt;">OK</span>', '<b>OK</b>', [], ['b' => ['tag' => 'span', 'attr' => ['data-price' => 1.5, 'title' => $stringable]]]];
        yield 'list of elements from the outermost to the innermost' => ['Ships on <span class="underline"><time datetime="2026-05-05">May 5</time></span>', 'Ships on <date>%date%</date>', ['%date%' => 'May 5'], ['date' => [['tag' => 'span', 'attr' => ['class' => 'underline']], ['tag' => 'time', 'attr' => ['datetime' => '2026-05-05']]]]];
        yield 'tag names with underscores and hyphens' => ['<a href="/login">sign in</a> or <a href="/register">sign up</a>', '<signin_link>sign in</signin_link> or <sign-up>sign up</sign-up>', [], ['signin_link' => ['tag' => 'a', 'attr' => ['href' => '/login']], 'sign-up' => ['tag' => 'a', 'attr' => ['href' => '/register']]]];
        yield 'custom element' => ['<my-element>OK</my-element>', '<b>OK</b>', [], ['b' => ['tag' => 'my-element']]];
        yield 'escapable raw text element' => ['<textarea>&lt;/textarea&gt;</textarea>', '<b>%value%</b>', ['%value%' => '</textarea>'], ['b' => ['tag' => 'textarea']]];

        yield 'no tags' => ['Tom &amp; Jerry &lt;3', 'Tom & Jerry <3'];
        yield 'empty message' => ['', ''];

        yield 'parameter with HTML is escaped' => ['Hello <b>&lt;script&gt;alert(1)&lt;/script&gt;</b>!', 'Hello <strong>%name%</strong>!', ['%name%' => '<script>alert(1)</script>'], ['strong' => ['tag' => 'b']]];
        yield 'parameter with a declared tag is escaped' => ['Hello <b>&lt;strong&gt;pwned&lt;/strong&gt;</b>!', 'Hello <strong>%name%</strong>!', ['%name%' => '<strong>pwned</strong>'], ['strong' => ['tag' => 'b']]];
        yield 'parameter with quotes and ampersands is escaped' => ['Hello <b>&quot;Tom&quot; &amp; &#039;Jerry&#039;</b>!', 'Hello <strong>%name%</strong>!', ['%name%' => '"Tom" & \'Jerry\''], ['strong' => ['tag' => 'b']]];
        yield 'parameter with an entity is escaped' => ['Hello <b>&amp;lt;b&amp;gt;</b>!', 'Hello <strong>%name%</strong>!', ['%name%' => '&lt;b&gt;'], ['strong' => ['tag' => 'b']]];
        yield 'stringable parameter is escaped' => ['Hello <b>&lt;i&gt;</b>!', 'Hello <strong>%name%</strong>!', ['%name%' => $stringable], ['strong' => ['tag' => 'b']]];
        yield 'translatable parameter is translated then escaped' => ['From &lt;b&gt;&lt;i&gt;&lt;/b&gt;', 'From %who%', ['%who%' => new TranslatableMessage('<b>%name%</b>', ['%name%' => '<i>'])], ['b' => ['tag' => 'strong']]];
        yield 'count' => ['<strong>3</strong> apples', '{1} <b>one</b> apple|]1,Inf[ <b>%count%</b> apples', ['%count%' => 3], ['b' => ['tag' => 'strong']]];

        yield 'HTML in the message is escaped' => ['&lt;script&gt;alert(1)&lt;/script&gt;&lt;img src=x onerror=alert(2)&gt; &quot;double&quot; &#039;single&#039;', '<script>alert(1)</script><img src=x onerror=alert(2)> "double" \'single\'', [], ['b' => ['tag' => 'strong']]];
        yield 'entities in the message are kept' => ['Tom &amp; Jerry &copy; 2026', 'Tom &amp; Jerry &copy; 2026'];
        yield 'undeclared tag is escaped' => ['Hello &lt;em&gt;Fabien&lt;/em&gt; and <b>you</b>', 'Hello <em>%name%</em> and <strong>you</strong>', ['%name%' => 'Fabien'], ['strong' => ['tag' => 'b']]];
        yield 'tag names are case-sensitive' => ['Hello &lt;STRONG&gt;you&lt;/STRONG&gt;', 'Hello <STRONG>you</STRONG>', [], ['strong' => ['tag' => 'b']]];
        yield 'tag with attributes in the message is not replaced' => ['Click &lt;link href=&quot;javascript:alert(1)&quot;&gt;here&lt;/link&gt;', 'Click <link href="javascript:alert(1)">here</link>', [], ['link' => ['tag' => 'a', 'attr' => ['href' => '/docs']]]];
        yield 'repeated tags' => ['<strong>one</strong>, <strong>two</strong>', '<b>one</b>, <b>two</b>', [], ['b' => ['tag' => 'strong']]];
        yield 'nested tags' => ['<a href="/docs">Read <strong>the docs</strong></a>', '<link>Read <b>the docs</b></link>', [], ['link' => ['tag' => 'a', 'attr' => ['href' => '/docs']], 'b' => ['tag' => 'strong']]];
        yield 'nested identical tags' => ['<strong>bold <strong>bolder</strong></strong>', '<b>bold <b>bolder</b></b>', [], ['b' => ['tag' => 'strong']]];
        yield 'whitespace inside tags' => ['<strong>a</strong> b<br>c<br>', '<b >a</b > b<br />c<br/>', [], ['b' => ['tag' => 'strong'], 'br' => ['tag' => 'br']]];
        yield 'void element' => ['Line 1<br>Line 2', 'Line 1<br>Line 2', [], ['br' => ['tag' => 'br']]];
        yield 'self-closing tag' => ['Icon: <i class="icon"></i>', 'Icon: <icon/>', [], ['icon' => ['tag' => 'i', 'attr' => ['class' => 'icon']]]];

        yield 'unclosed tag escapes the whole message' => ['Hello &lt;strong&gt;Fabien', 'Hello <strong>%name%', ['%name%' => 'Fabien'], ['strong' => ['tag' => 'b']]];
        yield 'unopened tag escapes the whole message' => ['Hello Fabien&lt;/strong&gt;', 'Hello %name%</strong>', ['%name%' => 'Fabien'], ['strong' => ['tag' => 'b']]];
        yield 'crossed tags escape the whole message' => ['&lt;a&gt;x&lt;b&gt;y&lt;/a&gt;z&lt;/b&gt;', '<a>x<b>y</a>z</b>', [], ['a' => ['tag' => 'em'], 'b' => ['tag' => 'strong']]];
        yield 'closed void element escapes the whole message' => ['Line 1&lt;br&gt;&lt;/br&gt;Line 2', 'Line 1<br></br>Line 2', [], ['br' => ['tag' => 'br']]];
    }

    #[DataProvider('provideInvalidTags')]
    public function testTransWithInvalidTags(array $tags, string $message)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new HtmlTranslator(new IdentityTranslator()))->trans('Tom & Jerry', [], null, null, $tags);
    }

    public static function provideInvalidTags(): iterable
    {
        $definition = 'The "b" tag must be an array with a "tag" key and an optional "attr" key, or a non-empty list of such arrays.';

        yield 'HTML string' => [['b' => '<strong>'], $definition];
        yield 'tag name string' => [['b' => 'strong'], $definition];
        yield 'integer' => [['b' => 42], $definition];
        yield 'empty list' => [['b' => []], $definition];
        yield 'missing tag' => [['b' => ['attr' => ['class' => 'x']]], $definition];
        yield 'non-string tag' => [['b' => ['tag' => 42]], $definition];
        yield 'unknown key' => [['b' => ['tag' => 'strong', 'class' => 'x']], $definition];
        yield 'non-array attr' => [['b' => ['tag' => 'strong', 'attr' => 'x']], $definition];
        yield 'invalid item in a list' => [['b' => [['tag' => 'strong'], 'em']], $definition];

        yield 'integer tag name' => [[['tag' => 'strong']], 'The "0" tag name must start with a letter and only contain letters, digits, "_" and "-".'];
        yield 'tag name with a space' => [['my tag' => ['tag' => 'strong']], 'The "my tag" tag name must start with a letter and only contain letters, digits, "_" and "-".'];

        yield 'HTML tag with brackets' => [['b' => ['tag' => '<strong>']], 'The "b" tag has an invalid HTML tag name "<strong>".'];
        yield 'HTML tag with a space' => [['b' => ['tag' => 'a href']], 'The "b" tag has an invalid HTML tag name "a href".'];

        foreach (['script', 'style', 'iframe', 'noembed', 'noframes', 'noscript', 'xmp', 'plaintext', 'SCRIPT'] as $element) {
            yield 'raw text element '.$element => [['b' => [['tag' => 'span'], ['tag' => $element]]], \sprintf('The "b" tag cannot use the "%s" HTML element as its content is not escaped.', $element)];
        }

        yield 'integer attribute name' => [['b' => ['tag' => 'button', 'attr' => ['disabled']]], 'The "b" tag has an invalid attribute name "0".'];
        yield 'attribute name with a space' => [['b' => ['tag' => 'a', 'attr' => ['on click' => 'x']]], 'The "b" tag has an invalid attribute name "on click".'];
        yield 'attribute name with a quote' => [['b' => ['tag' => 'a', 'attr' => ['a"b' => 'x']]], 'The "b" tag has an invalid attribute name "a"b".'];

        yield 'array attribute value' => [['b' => ['tag' => 'a', 'attr' => ['class' => ['x', 'y']]]], 'The "class" attribute of the "b" tag must be a scalar, a stringable object or null, "array" given.'];
    }

    public function testTransTranslatesTranslatableParametersInTheGivenLocale()
    {
        $translator = $this->createTranslator();

        $this->assertSame('De <strong>le &lt;monde&gt;</strong>', (new HtmlTranslator($translator))->trans('from', ['%who%' => new TranslatableMessage('world')], null, 'fr', ['b' => ['tag' => 'strong']]));
    }

    public function testTransEscapesGlobalParameters()
    {
        $translator = $this->createTranslator();
        $translator->addGlobalParameter('%site%', '<b>pwn</b>');

        $this->assertSame('Welcome to <strong>&lt;b&gt;pwn&lt;/b&gt;</strong>', (new HtmlTranslator($translator))->trans('welcome', [], null, null, ['b' => ['tag' => 'strong']]));
    }

    public function testTransEscapesGlobalParametersThroughDecorators()
    {
        $translator = $this->createTranslator();
        $translator->addGlobalParameter('%site%', '<b>pwn</b>');
        $translator = new DataCollectorTranslator(new LoggingTranslator($translator, $this->createStub(LoggerInterface::class)));

        $this->assertSame('Welcome to <strong>&lt;b&gt;pwn&lt;/b&gt;</strong>', (new HtmlTranslator($translator))->trans('welcome', [], null, null, ['b' => ['tag' => 'strong']]));
    }

    public function testTransEscapesGlobalParametersThroughPseudoLocalization()
    {
        $translator = $this->createTranslator();
        $translator->addGlobalParameter('%site%', '<b>pwn</b>');
        $translator = new PseudoLocalizationTranslator($translator, ['accents' => false, 'expansion_factor' => 1.0, 'brackets' => false, 'parse_html' => false]);

        $this->assertSame('Welcome to <strong>&lt;b&gt;pwn&lt;/b&gt;</strong>', (new HtmlTranslator($translator))->trans('welcome', [], null, null, ['b' => ['tag' => 'strong']]));
    }

    public function testTransTranslatesTranslatableGlobalParameters()
    {
        $translator = $this->createTranslator();
        $translator->addGlobalParameter('%site%', new TranslatableMessage('world'));

        $this->assertSame('Bienvenue sur <strong>le &lt;monde&gt;</strong>', (new HtmlTranslator($translator))->trans('welcome', [], null, 'fr', ['b' => ['tag' => 'strong']]));
    }

    public function testTransGivesPrecedenceToParametersOverGlobalParameters()
    {
        $translator = $this->createTranslator();
        $translator->addGlobalParameter('%site%', 'global');

        $this->assertSame('Welcome to <strong>&lt;i&gt;</strong>', (new HtmlTranslator($translator))->trans('welcome', ['%site%' => '<i>'], null, null, ['b' => ['tag' => 'strong']]));
    }

    public function testTransGoesThroughTheDataCollector()
    {
        $translator = new DataCollectorTranslator($this->createTranslator());

        $this->assertSame('Welcome to <strong>Symfony</strong>', (new HtmlTranslator($translator))->trans('welcome', ['%site%' => 'Symfony'], null, null, ['b' => ['tag' => 'strong']]));
        $this->assertSame(['welcome'], array_column($translator->getCollectedMessages(), 'id'));
    }

    public function testTransGoesThroughCustomDecorators()
    {
        $translator = new class($this->createTranslator()) implements TranslatorInterface {
            public function __construct(
                private TranslatorInterface $translator,
            ) {
            }

            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return strtoupper($this->translator->trans($id, $parameters, $domain, $locale));
            }

            public function getLocale(): string
            {
                return $this->translator->getLocale();
            }
        };

        $this->assertSame('WELCOME TO <strong>SYMFONY</strong>', (new HtmlTranslator($translator))->trans('welcome', ['%site%' => 'Symfony'], null, null, ['B' => ['tag' => 'strong']]));
    }

    public function testTransWithPseudoLocalization()
    {
        $translator = new PseudoLocalizationTranslator(new IdentityTranslator(), ['parse_html' => true, 'accents' => true, 'expansion_factor' => 1.0, 'brackets' => false]);

        $this->assertSame("ĥéļļö\u{2003}<strong>ƒöö</strong>", (new HtmlTranslator($translator))->trans('hello <b>foo</b>', [], null, null, ['b' => ['tag' => 'strong']]));
    }

    #[DataProvider('provideTransWithIntlMessages')]
    #[RequiresPhpExtension('intl')]
    public function testTransWithIntlMessages(string $expected, string $id, array $parameters)
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'hello' => 'Hello <strong>{name}</strong>!',
            'plural' => '{count, plural, one {<strong>#</strong> item} other {<strong>#</strong> items}}',
            'select' => '{gender, select, female {<strong>She</strong> replied} other {<strong>They</strong> replied}}',
            'apostrophe' => 'It\'\'s <strong>{name}</strong>',
        ], 'en', 'messages'.MessageCatalogue::INTL_DOMAIN_SUFFIX);

        $this->assertSame($expected, (new HtmlTranslator($translator))->trans($id, $parameters, null, null, ['strong' => ['tag' => 'b']]));
    }

    public static function provideTransWithIntlMessages(): iterable
    {
        yield 'argument' => ['Hello <b>&lt;i&gt;</b>!', 'hello', ['name' => '<i>']];
        yield 'plural (one)' => ['<b>1</b> item', 'plural', ['count' => 1]];
        yield 'plural (other)' => ['<b>1,234</b> items', 'plural', ['count' => 1234]];
        yield 'select' => ['<b>She</b> replied', 'select', ['gender' => 'female']];
        yield 'quoted apostrophe' => ['It&#039;s <b>Fabien</b>', 'apostrophe', ['name' => 'Fabien']];
    }

    private function createTranslator(): Translator
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['welcome' => 'Welcome to <b>%site%</b>', 'from' => 'From <b>%who%</b>', 'world' => 'the <world>'], 'en');
        $translator->addResource('array', ['welcome' => 'Bienvenue sur <b>%site%</b>', 'from' => 'De <b>%who%</b>', 'world' => 'le <monde>'], 'fr');

        return $translator;
    }
}
