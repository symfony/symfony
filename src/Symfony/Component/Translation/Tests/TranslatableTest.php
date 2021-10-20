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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Message\FormattedTranslatableMessage;
use Symfony\Component\Translation\Message\ImplodedTranslatableMessage;
use Symfony\Component\Translation\Message\NonTranslatableMessage;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatableInterface;

class TranslatableTest extends TestCase
{
    /**
     * @dataProvider getTransTests
     */
    public function testTrans(string $expected, TranslatableInterface $translatable, array $translation, string $locale)
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', $translation, $locale, '');

        $this->assertSame($expected, $translatable->trans($translator, $locale));
    }

    /**
     * @dataProvider getFlattenedTransTests
     */
    public function testFlattenedTrans($expected, $messages, $translatable)
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', $messages, 'fr', '');

        $this->assertSame($expected, $translatable->trans($translator, 'fr'));
    }

    public function testToString()
    {
        $this->assertSame('Symfony is great!', (string) new TranslatableMessage('Symfony is great!'));
        $this->assertSame('Symfony is %s!', (string) new FormattedTranslatableMessage('Symfony is %s!', new TranslatableMessage('great')));
        $this->assertSame('Symfony is great!', (string) new NonTranslatableMessage('Symfony is great!'));
    }

    public function getTransTests()
    {
        return [
            ['Symfony est super !', new TranslatableMessage('Symfony is great!', [], ''), [
                'Symfony is great!' => 'Symfony est super !',
            ], 'fr'],
            ['Symfony est awesome !', new TranslatableMessage('Symfony is %what%!', ['%what%' => 'awesome'], ''), [
                'Symfony is %what%!' => 'Symfony est %what% !',
            ], 'fr'],
            ['Symfony est superbe !', new TranslatableMessage('Symfony is %what%!', ['%what%' => new TranslatableMessage('awesome', [], '')], ''), [
                'Symfony is %what%!' => 'Symfony est %what% !',
                'awesome' => 'superbe',
            ], 'fr'],
            ['Symfony est super ! 100 times !', new FormattedTranslatableMessage('%s %d times !', new TranslatableMessage('Symfony is great!', [], ''), 100), [
                'Symfony is great!' => 'Symfony est super !',
            ], 'fr'],
            ['Symfony est superbe', new ImplodedTranslatableMessage(' ', 'Symfony', new TranslatableMessage('is', [] ,''), new TranslatableMessage('super', [] ,'')), [
                'is' => 'est',
                'super' => 'superbe'
            ], 'fr'],
            ['Symfony is great!', new NonTranslatableMessage('Symfony is great!'), [
                'Symfony is great!' => 'Symfony est super !',
            ], 'fr']
        ];
    }

    public function getFlattenedTransTests()
    {
        $messages = [
            'symfony' => [
                'is' => [
                    'great' => 'Symfony est super!',
                ],
            ],
            'foo' => [
                'bar' => [
                    'baz' => 'Foo Bar Baz',
                ],
                'baz' => 'Foo Baz',
            ],
        ];

        return [
            ['Symfony est super!', $messages, new TranslatableMessage('symfony.is.great', [], '')],
            ['Foo Bar Baz', $messages, new TranslatableMessage('foo.bar.baz', [], '')],
            ['Foo Baz', $messages, new TranslatableMessage('foo.baz', [], '')],
            ['Result: Foo Baz', $messages, new FormattedTranslatableMessage('Result: %s', new TranslatableMessage('foo.baz', [], ''))],
            ['Foo Baz ~ Foo Bar Baz', $messages, new ImplodedTranslatableMessage(' ~ ', new TranslatableMessage('foo.baz', [], ''), new TranslatableMessage('foo.bar.baz', [] , ''))],
            ['foo.bar.baz', $messages, new NonTranslatableMessage('foo.bar.baz')]
        ];
    }
}
