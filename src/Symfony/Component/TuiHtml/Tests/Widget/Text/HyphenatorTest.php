<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Widget\Text;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TuiHtml\Widget\Text\Hyphenator;
use Symfony\Component\TuiHtml\Widget\Text\HyphenatorRegistry;

final class HyphenatorTest extends TestCase
{
    public function testLiangPatterns()
    {
        // The patterns of Liang's thesis for "hyphenation"
        $hyphenator = new Hyphenator('hy3ph he2n hena4 hen5at 1na n2at 1tio 2io o2n', '', 2, 3);
        $this->assertSame([2, 6], $hyphenator->hyphenate('hyphenation'));
        $this->assertSame([2, 6], $hyphenator->hyphenate('Hyphenation'), 'Whatever the case.');
        $this->assertSame([], $hyphenator->hyphenate('hyp'), 'Too short for the least characters on both sides.');
        $this->assertSame([], $hyphenator->hyphenate(str_repeat('hyphenation', 10)), 'Longer than any word.');
    }

    public function testExceptionsAndLeastCharacters()
    {
        $hyphenator = new Hyphenator('1ble', 'ta-ble pro-ject', 1, 1);
        $this->assertSame([2], $hyphenator->hyphenate('table'));
        $this->assertSame([3], $hyphenator->hyphenate('project'));
        $this->assertSame([], (new Hyphenator('a1b'))->hyphenate('ab'), 'Two characters on each side by default.');
    }

    public static function provideBundledLanguages(): iterable
    {
        yield ['en', 'hyphenation', 'hy-phen-ation'];
        yield ['en-GB', 'associate', 'as-so-ciate'];
        yield ['fr', 'informatique', 'in-for-ma-tique'];
        yield ['fr-CA', 'développement', 'dé-ve-lop-pe-ment'];
        yield ['de', 'Silbentrennung', 'Sil-ben-tren-nung'];
        yield ['es', 'murciélago', 'mur-cié-la-go'];
    }

    #[DataProvider('provideBundledLanguages')]
    public function testBundledLanguages(string $language, string $word, string $expected)
    {
        $breaks = (new HyphenatorRegistry())->get($language)?->hyphenate($word) ?? [];
        $hyphenated = '';
        foreach (mb_str_split($word) as $i => $character) {
            $hyphenated .= (\in_array($i, $breaks, true) ? '-' : '').$character;
        }
        $this->assertSame($expected, $hyphenated);
    }

    public function testRegisteredLanguages()
    {
        $directory = sys_get_temp_dir().'/tui-hyphenation-'.bin2hex(random_bytes(4));
        mkdir($directory);
        try {
            file_put_contents($directory.'/hyph-xx.pat.txt', "1ba\n");
            file_put_contents($directory.'/hyph-xx.hyp.txt', "aaa-aaa\n");
            $registry = (new HyphenatorRegistry())->register('xx', $directory.'/hyph-xx.pat.txt');
            $this->assertTrue($registry->has('xx-YY'));
            $this->assertSame([2], $registry->get('xx')?->hyphenate('abba'));
            $this->assertSame([3], $registry->get('xx-YY')?->hyphenate('aaaaaa'), 'With the exceptions next to the patterns.');
            $this->assertNull($registry->get('it'));
            $this->assertNull($registry->get(''));
        } finally {
            array_map(unlink(...), glob($directory.'/*') ?: []);
            rmdir($directory);
        }
    }

    public function testPhpFilesOfPatternsOrCompiledOnes()
    {
        $directory = sys_get_temp_dir().'/tui-hyphenation-'.bin2hex(random_bytes(4));
        mkdir($directory);
        try {
            file_put_contents($directory.'/source.php', '<?php return '.var_export(['patterns' => '1ba', 'exceptions' => 'aaa-aaa', 'left' => 1, 'right' => 1], true).';');
            $compiled = Hyphenator::load($directory.'/source.php')->compiled();
            file_put_contents($directory.'/compiled.php', '<?php return '.var_export(['left' => 1, 'right' => 1, 'longest' => $compiled['longest'], 'letters' => implode("\n", array_keys($compiled['patterns'])), 'digits' => implode("\n", $compiled['patterns']), 'exceptions' => $compiled['exceptions']], true).';');
            foreach (['source.php', 'compiled.php'] as $file) {
                $hyphenator = Hyphenator::load($directory.'/'.$file);
                $this->assertSame([2], $hyphenator->hyphenate('abba'), $file);
                $this->assertSame([3], $hyphenator->hyphenate('aaaaaa'), $file.': its exceptions.');
            }
        } finally {
            array_map(unlink(...), glob($directory.'/*') ?: []);
            rmdir($directory);
        }
    }
}
