<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Translation\Exception\InvalidResourceException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Translation\Loader\QtFileLoader;
use Symfony\Component\Translation\Tests\Fixtures\MutatingResourceStream;

class QtFileLoaderTest extends TestCase
{
    public function testLoad()
    {
        $loader = new QtFileLoader();
        $resource = __DIR__.'/../Fixtures/resources.ts';
        $catalogue = $loader->load($resource, 'en', 'resources');

        $this->assertEquals([
            'foo' => 'bar',
            'foo_bar' => 'foobar',
            'bar_foo' => 'barfoo',
        ], $catalogue->all('resources'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    public function testLoadNonExistingResource()
    {
        $this->expectException(NotFoundResourceException::class);

        (new QtFileLoader())->load(__DIR__.'/../Fixtures/non-existing.ts', 'en', 'domain1');
    }

    public function testLoadNonLocalResource()
    {
        $this->expectException(InvalidResourceException::class);

        (new QtFileLoader())->load('http://domain1.com/resources.ts', 'en', 'domain1');
    }

    public function testLoadInvalidResource()
    {
        $this->expectException(InvalidResourceException::class);

        (new QtFileLoader())->load(__DIR__.'/../Fixtures/invalid-xml-resources.xlf', 'en', 'domain1');
    }

    public function testLoadEmptyResource()
    {
        $resource = __DIR__.'/../Fixtures/empty.xlf';

        $this->expectException(InvalidResourceException::class);
        $this->expectExceptionMessage(\sprintf('Unable to load "%s".', $resource));

        (new QtFileLoader())->load($resource, 'en', 'domain1');
    }

    public function testXmlInputIsCapturedBeforeItIsRead()
    {
        $contents = '<?xml version="1.0"?><TS><context><name>messages</name><message><source>key</source><translation>v1</translation></message></context></TS>';
        $path = tempnam(sys_get_temp_dir(), 'sf-translation-capture-');
        file_put_contents($path, $contents);
        MutatingResourceStream::$path = $path;
        MutatingResourceStream::$mutated = false;
        stream_wrapper_register('translationcapture', MutatingResourceStream::class);
        try {
            $catalogue = (new QtFileLoader())->load('translationcapture://messages', 'en');
            self::assertTrue(MutatingResourceStream::$mutated);
            self::assertSame('v1', $catalogue->get('key'));
            self::assertStringContainsString('v2', file_get_contents($path));
            self::assertCount(1, $catalogue->getResources());
            self::assertFalse($catalogue->getResources()[0]->isFresh(time() + 10));
        } finally {
            stream_wrapper_unregister('translationcapture');
            unlink($path);
        }
    }
}
