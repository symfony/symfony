<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Formatter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Component\Translation\Formatter\IntlFormatter;
use Symfony\Component\Translation\Formatter\IntlFormatterInterface;

#[RequiresPhpExtension('intl')]
class IntlFormatterTest extends TestCase
{
    #[DataProvider('provideDataForFormat')]
    public function testFormat($expected, $message, $arguments)
    {
        $this->assertEquals($expected, trim((new IntlFormatter())->formatIntl($message, 'en', $arguments)));
    }

    #[DataProvider('provideMarkup')]
    public function testMarkup(string $expected, string $message, array $parameters = [])
    {
        $formatter = new IntlFormatter();

        $this->assertSame($expected, $formatter->formatIntl($message, 'en', $parameters));
        $this->assertSame($expected, $formatter->formatIntl($message, 'en', $parameters));
    }

    public static function provideMarkup(): iterable
    {
        yield ['#2{#b/}', "{n,number,'#'0}{#b/}", ['n' => 2]];
        yield ['year 1970{#b/}', "{n,date,'year' yyyy}{#b/}", ['n' => gmmktime(12, 0, 0, 6, 15, 1970)]];
        yield ['{2}{#b/}', "{n,number,'{'0'}'}{#b/}", ['n' => 2]];
        yield ['{#b}1.5{/b}', '{#b}{n, number}{/b}', ['n' => 1.5]];
        yield ["'#' {#b/}", "'#' {#b/}"];
        yield ["'|' {#b/}", "'|' {#b/}"];
        yield ['{#b}2nd{/b}', '{n, selectordinal, one {{#b}#st{/b}} two {{#b}#nd{/b}} other {{#b}#th{/b}}}', ['n' => 2]];
        yield ['{#b}many{/b}', '{n, choice, 0#none|1#one|1<{#b}many{/b}}', ['n' => 2]];
        yield ['{#b }x{/b }{#br /}', '{#b }x{/b }{#br /}'];
        yield ['{#b}#{/b} 2', "{数量, plural, other {{#b}'#'{/b} #}}", ['数量' => 2]];
        yield ['foo {#bar} baz', 'foo {#bar} baz'];
        yield ['{#b}Ada{/b}{#br/}', '{#b}{name}{/b}{#br/}', ['name' => 'Ada']];
        yield ['{#a}{#b}x{/b}{/a}', '{#a}{#b}x{/b}{/a}'];
        yield ['{#b}x{/b}', "'{#b}'x'{/b}'"];
        yield ['{#a}{#b/}{/a}', "{#a}'{#b/}'{/a}"];
        yield ["It's {#b}Ada{/b}", "It's {#b}{name}{/b}", ['name' => 'Ada']];
        yield ["It's {#b}Ada{/b}", "It''s {#b}{name}{/b}", ['name' => 'Ada']];
        yield ["'{#b}Ada{/b}'", "''{#b}{name}{/b}''", ['name' => 'Ada']];
        yield ['{#b}{name}{/b}', "'{#b}{name}{/b}'"];
        yield ["{#b}It's {name}{/b}", "'{#b}It''s {name}{/b}'"];
        yield ["{#b}It's", "'{#b}It''s"];
        yield ['{#b}2{/b} items', '{n, plural, one {{#b}#{/b} item} other {{#b}#{/b} items}}', ['n' => 2]];
        yield ['{#b}2{/b}', '{gender, select, female {{n, plural, one {{#b}#{/b}} other {{#b}#{/b}}}} other {{#b}other{/b}}}', ['gender' => 'female', 'n' => 2]];
        yield ['{#b}#{/b} 2', "{n, plural, other {{#b}'#'{/b} #}}", ['n' => 2]];
    }

    public function testInvalidFormat()
    {
        $this->expectException(InvalidArgumentException::class);
        (new IntlFormatter())->formatIntl('{foo', 'en', [2]);
    }

    public function testFormatWithNamedArguments()
    {
        if (version_compare(\INTL_ICU_VERSION, '4.8', '<')) {
            $this->markTestSkipped('Format with named arguments can only be run with ICU 4.8 or higher and PHP >= 5.5');
        }

        $chooseMessage = <<<'_MSG_'
            {gender_of_host, select,
              female {{num_guests, plural, offset:1
                  =0 {{host} does not give a party.}
                  =1 {{host} invites {guest} to her party.}
                  =2 {{host} invites {guest} and one other person to her party.}
                 other {{host} invites {guest} as one of the # people invited to her party.}}}
              male   {{num_guests, plural, offset:1
                  =0 {{host} does not give a party.}
                  =1 {{host} invites {guest} to his party.}
                  =2 {{host} invites {guest} and one other person to his party.}
                 other {{host} invites {guest} as one of the # people invited to his party.}}}
              other {{num_guests, plural, offset:1
                  =0 {{host} does not give a party.}
                  =1 {{host} invites {guest} to their party.}
                  =2 {{host} invites {guest} and one other person to their party.}
                 other {{host} invites {guest} as one of the # people invited to their party.}}}}
            _MSG_;

        $message = (new IntlFormatter())->formatIntl($chooseMessage, 'en', [
            'gender_of_host' => 'male',
            'num_guests' => 10,
            'host' => 'Fabien',
            'guest' => 'Guilherme',
        ]);

        $this->assertEquals('Fabien invites Guilherme as one of the 9 people invited to his party.', $message);
    }

    public static function provideDataForFormat()
    {
        return [
            [
                'There is one apple',
                'There is one apple',
                [],
            ],
            [
                '4,560 monkeys on 123 trees make 37.073 monkeys per tree',
                '{0,number,integer} monkeys on {1,number,integer} trees make {2,number} monkeys per tree',
                [4560, 123, 4560 / 123],
            ],
            [
                '',
                '',
                [],
            ],
            [
                'There is one apple',
                'There is one apple',
                ['apple' => 'pear'],
            ],
            [
                "It's one apple",
                "It''s one apple",
                [],
            ],
            [
                'One apple }',
                'One apple }',
                [],
            ],
            [
                "L'utilisateur n'existe pas",
                "L'utilisateur n'existe pas",
                [],
            ],
            [
                'One } apple',
                "One '}' apple",
                [],
            ],
        ];
    }

    #[DataProvider('percentAndBracketsAreTrimmedProvider')]
    public function testPercentsAndBracketsAreTrimmed(string $expected, string $message, array $parameters)
    {
        $formatter = new IntlFormatter();
        $this->assertInstanceof(IntlFormatterInterface::class, $formatter);
        $this->assertSame($expected, $formatter->formatIntl($message, 'en', $parameters));
    }

    public static function percentAndBracketsAreTrimmedProvider(): array
    {
        return [
            ['Hello Fab', 'Hello {name}', ['name' => 'Fab']],
            ['Hello Fab', 'Hello {name}', ['%name%' => 'Fab']],
            ['Hello Fab', 'Hello {name}', ['{{ name }}' => 'Fab']],
        ];
    }
}
