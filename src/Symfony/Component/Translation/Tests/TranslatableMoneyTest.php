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
use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\TranslatableMoney;
use Symfony\Component\Translation\Translator;

#[RequiresPhpExtension('intl')]
class TranslatableMoneyTest extends TestCase
{
    #[DataProvider('provideTrans')]
    public function testTrans(string $expected, TranslatableMoney $money, string $locale)
    {
        $this->assertSame($expected, $money->trans(new Translator('en'), $locale));
    }

    public static function provideTrans(): iterable
    {
        yield 'euros in English' => ['€9,988,776.65', new TranslatableMoney(9988776.65, 'EUR'), 'en'];
        yield 'dollars in English' => ['$100.00', new TranslatableMoney(100, 'USD'), 'en'];
        yield 'yens in English' => ['¥100', new TranslatableMoney(100, 'JPY'), 'en'];
        yield 'euros in French' => ["100,00\u{A0}€", new TranslatableMoney(100, 'EUR'), 'fr'];
        yield 'dollars in French' => ["100,00\u{A0}\$US", new TranslatableMoney(100, 'USD'), 'fr'];
    }

    public function testTransUsesTheLocaleOfTheTranslator()
    {
        $money = new TranslatableMoney(100, 'EUR');

        $this->assertSame("100,00\u{A0}€", $money->trans(new Translator('fr')));
    }

    public function testTransAsParameter()
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['value_of_object' => 'This artifact is worth {value}'], 'en', 'messages+intl-icu');
        $translator->addResource('array', ['value_of_object' => 'Cet objet vaut {value}'], 'fr', 'messages+intl-icu');

        $parameters = ['value' => new TranslatableMoney(100, 'EUR')];

        $this->assertSame('This artifact is worth €100.00', $translator->trans('value_of_object', $parameters));
        $this->assertSame("Cet objet vaut 100,00\u{A0}€", $translator->trans('value_of_object', $parameters, null, 'fr'));
        $this->assertSame("Cet objet vaut 100,00\u{A0}€", (new TranslatableMessage('value_of_object', $parameters))->trans($translator, 'fr'));
    }

    public function testTransThrowsOnInvalidCurrency()
    {
        $money = new TranslatableMoney(100, 'EU');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to format money in currency "EU"');

        $money->trans(new Translator('en'));
    }
}
