<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Widget\Util;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Widget\Util\UrlResolver;

class UrlResolverTest extends TestCase
{
    #[DataProvider('provideReferences')]
    public function testResolve(string $reference, string $expected)
    {
        $this->assertSame($expected, UrlResolver::resolve($reference, 'http://a/b/c/d;p?q'));
    }

    /**
     * The examples of RFC 3986, section 5.4, but for the fragment alone, which stays in the page.
     */
    public static function provideReferences(): iterable
    {
        yield ['g:h', 'g:h'];
        yield ['g', 'http://a/b/c/g'];
        yield ['./g', 'http://a/b/c/g'];
        yield ['g/', 'http://a/b/c/g/'];
        yield ['/g', 'http://a/g'];
        yield ['//g', 'http://g'];
        yield ['?y', 'http://a/b/c/d;p?y'];
        yield ['g?y', 'http://a/b/c/g?y'];
        yield ['#s', '#s'];
        yield ['g#s', 'http://a/b/c/g#s'];
        yield ['g?y#s', 'http://a/b/c/g?y#s'];
        yield [';x', 'http://a/b/c/;x'];
        yield ['g;x', 'http://a/b/c/g;x'];
        yield ['g;x?y#s', 'http://a/b/c/g;x?y#s'];
        yield ['', ''];
        yield ['.', 'http://a/b/c/'];
        yield ['./', 'http://a/b/c/'];
        yield ['..', 'http://a/b/'];
        yield ['../', 'http://a/b/'];
        yield ['../g', 'http://a/b/g'];
        yield ['../..', 'http://a/'];
        yield ['../../', 'http://a/'];
        yield ['../../g', 'http://a/g'];
        yield ['../../../g', 'http://a/g'];
        yield ['../../../../g', 'http://a/g'];
        yield ['/./g', 'http://a/g'];
        yield ['/../g', 'http://a/g'];
        yield ['g.', 'http://a/b/c/g.'];
        yield ['.g', 'http://a/b/c/.g'];
        yield ['g..', 'http://a/b/c/g..'];
        yield ['..g', 'http://a/b/c/..g'];
        yield ['./../g', 'http://a/b/g'];
        yield ['./g/.', 'http://a/b/c/g/'];
        yield ['g/./h', 'http://a/b/c/g/h'];
        yield ['g/../h', 'http://a/b/c/h'];
        yield ['g;x=1/./y', 'http://a/b/c/g;x=1/y'];
        yield ['g;x=1/../y', 'http://a/b/c/y'];
        yield ['g?y/./x', 'http://a/b/c/g?y/./x'];
        yield ['g?y/../x', 'http://a/b/c/g?y/../x'];
        yield ['g#s/./x', 'http://a/b/c/g#s/./x'];
        yield ['g#s/../x', 'http://a/b/c/g#s/../x'];
        yield ['http:g', 'http:g'];
    }

    public function testBaseWithoutPathOrScheme()
    {
        $this->assertSame('https://symfony.com/doc', UrlResolver::resolve('doc', 'https://symfony.com'));
        $this->assertSame('doc', UrlResolver::resolve('doc', '/relative/base'), 'A base without a scheme resolves nothing.');
    }
}
