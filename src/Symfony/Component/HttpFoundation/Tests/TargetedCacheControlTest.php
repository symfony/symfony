<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class TargetedCacheControlTest extends TestCase
{
    public function testItWritesATargetedHeader()
    {
        $response = new Response();
        $response->cacheControl('CDN')->setMaxAge(3600);

        $this->assertSame('max-age=3600', $response->headers->get('CDN-Cache-Control'));
    }

    public function testItLeavesTheDefaultCacheControlAlone()
    {
        $response = new Response();
        $response->setMaxAge(60)->setPrivate();
        $response->cacheControl('CDN')->setMaxAge(3600);

        $this->assertSame('max-age=60, private', $response->headers->get('Cache-Control'));
        $this->assertSame('max-age=3600', $response->headers->get('CDN-Cache-Control'));
    }

    public function testTargetsAreIndependent()
    {
        $response = new Response();
        $response->cacheControl('CDN')->setMaxAge(3600);
        $response->cacheControl('Varnish')->setMaxAge(60);

        $this->assertSame('max-age=3600', $response->headers->get('CDN-Cache-Control'));
        $this->assertSame('max-age=60', $response->headers->get('Varnish-Cache-Control'));
    }

    public function testPublicAndPrivateReplaceEachOther()
    {
        $response = new Response();
        $cacheControl = $response->cacheControl('CDN');

        $cacheControl->setPublic();
        $this->assertSame('public', $response->headers->get('CDN-Cache-Control'));

        $cacheControl->setPrivate();
        $this->assertSame('private', $response->headers->get('CDN-Cache-Control'));

        $cacheControl->setPublic();
        $this->assertSame('public', $response->headers->get('CDN-Cache-Control'));
    }

    public function testImmutableAndNoStoreCanBeUnset()
    {
        $response = new Response();
        $cacheControl = $response->cacheControl('CDN')->setImmutable()->setNoStore();

        $this->assertSame('immutable, no-store', $response->headers->get('CDN-Cache-Control'));

        $cacheControl->setImmutable(false)->setNoStore(false);
        $this->assertFalse($response->headers->has('CDN-Cache-Control'));
    }

    public function testTheHeaderIsRemovedWhenTheLastDirectiveGoes()
    {
        $response = new Response();
        $response->cacheControl('CDN')->setMaxAge(10)->remove('max-age');

        $this->assertFalse($response->headers->has('CDN-Cache-Control'));
    }

    public function testArbitraryDirectives()
    {
        $response = new Response();
        $cacheControl = $response->cacheControl('CDN')->set('stale-while-revalidate', '30')->set('proprietary');

        $this->assertSame('proprietary, stale-while-revalidate=30', $response->headers->get('CDN-Cache-Control'));
        $this->assertTrue($cacheControl->has('proprietary'));
        $this->assertSame('30', $cacheControl->get('stale-while-revalidate'));
        $this->assertNull($cacheControl->get('max-age'));
        $this->assertSame(['proprietary' => true, 'stale-while-revalidate' => '30'], $cacheControl->all());
    }

    public function testDirectiveNamesAreCaseInsensitive()
    {
        $response = new Response();
        $cacheControl = $response->cacheControl('CDN')->set('Max-Age', '60');

        $this->assertSame('max-age=60', $response->headers->get('CDN-Cache-Control'));
        $this->assertTrue($cacheControl->has('MAX-AGE'));
        $this->assertSame('60', $cacheControl->get('Max-Age'));

        $cacheControl->remove('MAX-Age');
        $this->assertFalse($response->headers->has('CDN-Cache-Control'));
    }

    public function testSettingADirectiveToFalseRemovesIt()
    {
        $response = new Response();
        $cacheControl = $response->cacheControl('CDN')->setMaxAge(60)->set('no-store');

        $cacheControl->set('no-store', false);
        $this->assertSame('max-age=60', $response->headers->get('CDN-Cache-Control'));
        $this->assertFalse($cacheControl->has('no-store'));
    }

    public function testItReadsAHeaderSetByHand()
    {
        $response = new Response();
        $response->headers->set('CDN-Cache-Control', 'max-age=120, public');

        $cacheControl = $response->cacheControl('CDN');
        $this->assertSame('120', $cacheControl->get('max-age'));
        $this->assertTrue($cacheControl->has('public'));

        $cacheControl->setMaxAge(240);
        $this->assertSame('max-age=240, public', $response->headers->get('CDN-Cache-Control'));
    }

    public function testItReadsAHeaderSetAsSeveralFieldLines()
    {
        $response = new Response();
        $response->headers->set('CDN-Cache-Control', 'max-age=120');
        $response->headers->set('CDN-Cache-Control', 'public', false);

        $cacheControl = $response->cacheControl('CDN');
        $this->assertSame(['max-age' => '120', 'public' => true], $cacheControl->all());

        $cacheControl->setImmutable();
        $this->assertSame('immutable, max-age=120, public', $response->headers->get('CDN-Cache-Control'));
    }

    public function testTheTargetIsCaseInsensitiveForTheCache()
    {
        $response = new Response();
        $response->cacheControl('cdn')->setMaxAge(60);

        // HTTP header names are case insensitive, so the header bag finds it either way
        $this->assertSame('max-age=60', $response->headers->get('CDN-Cache-Control'));
    }

    #[DataProvider('provideInvalidTargets')]
    public function testItRejectsAnInvalidTarget(string $target)
    {
        $response = new Response();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a valid token');

        $response->cacheControl($target);
    }

    public static function provideInvalidTargets(): iterable
    {
        yield 'empty' => [''];
        yield 'space' => ['my cdn'];
        yield 'colon' => ['CDN:'];
        yield 'slash' => ['CDN/1'];
        yield 'trailing newline' => ["CDN\n"];
    }
}
