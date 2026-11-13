<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TuiHtml\Style\Density;
use Symfony\Component\TuiHtml\Style\MediaContext;
use Symfony\Component\TuiHtml\Twig\TuiExtension;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class TuiExtensionTest extends TestCase
{
    #[DataProvider('provideValues')]
    public function testCondensed(mixed $value, Density|\Closure $global, bool $expected)
    {
        $twig = new Environment(new ArrayLoader(['t' => '{{ value is condensed ? "yes" : "no" }}']));
        $twig->addExtension(new TuiExtension($global));
        $this->assertSame($expected ? 'yes' : 'no', $twig->render('t', ['value' => $value]));
    }

    public static function provideValues(): iterable
    {
        yield 'density name' => ['condensed', Density::Regular, true];
        yield 'regular name' => ['regular', Density::Condensed, false];
        yield 'attributes array' => [['data-density' => 'condensed'], Density::Regular, true];
        yield 'attributes array without density' => [['class' => 'card'], Density::Condensed, true];
        yield 'object with all()' => [new class {
            public function all(): array
            {
                return ['data-density' => 'condensed'];
            }
        }, Density::Regular, true];
        yield 'array access' => [new \ArrayObject(['data-density' => 'regular']), Density::Condensed, false];
        yield 'null falls back' => [null, Density::Regular, false];
        yield 'invalid density falls back' => ['tiny', Density::Condensed, true];
        yield 'media context closure' => [null, static fn () => new MediaContext(80, 24, \Symfony\Component\TuiHtml\Style\ColorScheme::Dark, Density::Condensed), true];
        yield 'density closure' => [[], static fn () => Density::Regular, false];
    }
}
