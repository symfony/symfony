<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\OutputWrapper;

class OutputWrapperTest extends TestCase
{
    /**
     * @dataProvider textProvider
     */
    public function testBasicWrap(string $text, int $width, ?bool $allowCutUrls, string $expected)
    {
        $wrapper = new OutputWrapper();
        if (\is_bool($allowCutUrls)) {
            $wrapper->setAllowCutUrls($allowCutUrls);
        }
        $result = $wrapper->wrap($text, $width);
        $this->assertEquals($expected, $result);
    }

    public static function textProvider(): iterable
    {
        $baseTextWithUtf8AndUrl = 'Árvíztűrőtükörfúrógép https://github.com/symfony/symfony Lorem ipsum <comment>dolor</comment> sit amet, consectetur adipiscing elit. Praesent vestibulum nulla quis urna maximus porttitor. Donec ullamcorper risus at <error>libero ornare</error> efficitur.';

        yield 'Default URL cut' => [
            $baseTextWithUtf8AndUrl,
            20,
            null,
            <<<'EOS'
            Árvíztűrőtükörfúrógé
            p https://github.com/symfony/symfony Lorem ipsum
            <comment>dolor</comment> sit amet,
            consectetur
            adipiscing elit.
            Praesent vestibulum
            nulla quis urna
            maximus porttitor.
            Donec ullamcorper
            risus at <error>libero
            ornare</error> efficitur.
            EOS,
        ];

        yield 'Allow URL cut' => [
            $baseTextWithUtf8AndUrl,
            20,
            true,
            <<<'EOS'
            Árvíztűrőtükörfúrógé
            p
            https://github.com/s
            ymfony/symfony Lorem
            ipsum <comment>dolor</comment> sit
            amet, consectetur
            adipiscing elit.
            Praesent vestibulum
            nulla quis urna
            maximus porttitor.
            Donec ullamcorper
            risus at <error>libero
            ornare</error> efficitur.
            EOS,
        ];
    }
}
