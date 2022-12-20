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
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Translation\Exception\LogicException;
use Symfony\Component\Translation\MessageCatalogue;

class MessageCatalogueTest extends TestCase
{
    public function testGetLocale()
    {
        $catalogue = new MessageCatalogue('en');

        self::assertEquals('en', $catalogue->getLocale());
    }

    public function testGetDomains()
    {
        $catalogue = new MessageCatalogue('en', ['domain1' => [], 'domain2' => [], 'domain2+intl-icu' => [], 'domain3+intl-icu' => []]);

        self::assertEquals(['domain1', 'domain2', 'domain3'], $catalogue->getDomains());
    }

    public function testAll()
    {
        $catalogue = new MessageCatalogue('en', $messages = ['domain1' => ['foo' => 'foo'], 'domain2' => ['bar' => 'bar']]);

        self::assertEquals(['foo' => 'foo'], $catalogue->all('domain1'));
        self::assertEquals([], $catalogue->all('domain88'));
        self::assertEquals($messages, $catalogue->all());

        $messages = ['domain1+intl-icu' => ['foo' => 'bar']] + $messages + [
            'domain2+intl-icu' => ['bar' => 'foo'],
            'domain3+intl-icu' => ['biz' => 'biz'],
        ];
        $catalogue = new MessageCatalogue('en', $messages);

        self::assertEquals(['foo' => 'bar'], $catalogue->all('domain1'));
        self::assertEquals(['bar' => 'foo'], $catalogue->all('domain2'));
        self::assertEquals(['biz' => 'biz'], $catalogue->all('domain3'));

        $messages = [
            'domain1' => ['foo' => 'bar'],
            'domain2' => ['bar' => 'foo'],
            'domain3' => ['biz' => 'biz'],
        ];
        self::assertEquals($messages, $catalogue->all());
    }

    public function testAllIntlIcu()
    {
        $messages = [
            'domain1+intl-icu' => ['foo' => 'bar'],
            'domain2+intl-icu' => ['bar' => 'foo'],
            'domain2' => ['biz' => 'biz'],
        ];
        $catalogue = new MessageCatalogue('en', $messages);

        // separated domains
        self::assertSame(['foo' => 'bar'], $catalogue->all('domain1+intl-icu'));
        self::assertSame(['bar' => 'foo'], $catalogue->all('domain2+intl-icu'));

        // merged, intl-icu ignored
        self::assertSame(['bar' => 'foo', 'biz' => 'biz'], $catalogue->all('domain2'));

        // intl-icu ignored
        $messagesExpected = [
            'domain1' => ['foo' => 'bar'],
            'domain2' => ['bar' => 'foo', 'biz' => 'biz'],
        ];
        self::assertSame($messagesExpected, $catalogue->all());
    }

    public function testHas()
    {
        $catalogue = new MessageCatalogue('en', ['domain1' => ['foo' => 'foo'], 'domain2+intl-icu' => ['bar' => 'bar']]);

        self::assertTrue($catalogue->has('foo', 'domain1'));
        self::assertTrue($catalogue->has('bar', 'domain2'));
        self::assertFalse($catalogue->has('bar', 'domain1'));
        self::assertFalse($catalogue->has('foo', 'domain88'));
    }

    public function testGetSet()
    {
        $catalogue = new MessageCatalogue('en', ['domain1' => ['foo' => 'foo'], 'domain2' => ['bar' => 'bar'], 'domain2+intl-icu' => ['bar' => 'foo']]);
        $catalogue->set('foo1', 'foo1', 'domain1');

        self::assertEquals('foo', $catalogue->get('foo', 'domain1'));
        self::assertEquals('foo1', $catalogue->get('foo1', 'domain1'));
        self::assertEquals('foo', $catalogue->get('bar', 'domain2'));
    }

    public function testAdd()
    {
        $catalogue = new MessageCatalogue('en', ['domain1' => ['foo' => 'foo'], 'domain2' => ['bar' => 'bar']]);
        $catalogue->add(['foo1' => 'foo1'], 'domain1');

        self::assertEquals('foo', $catalogue->get('foo', 'domain1'));
        self::assertEquals('foo1', $catalogue->get('foo1', 'domain1'));

        $catalogue->add(['foo' => 'bar'], 'domain1');
        self::assertEquals('bar', $catalogue->get('foo', 'domain1'));
        self::assertEquals('foo1', $catalogue->get('foo1', 'domain1'));

        $catalogue->add(['foo' => 'bar'], 'domain88');
        self::assertEquals('bar', $catalogue->get('foo', 'domain88'));
    }

    public function testAddIntlIcu()
    {
        $catalogue = new MessageCatalogue('en', ['domain1+intl-icu' => ['foo' => 'foo']]);
        $catalogue->add(['foo1' => 'foo1'], 'domain1');
        $catalogue->add(['foo' => 'bar'], 'domain1');

        self::assertSame('bar', $catalogue->get('foo', 'domain1'));
        self::assertSame('foo1', $catalogue->get('foo1', 'domain1'));
    }

    public function testReplace()
    {
        $catalogue = new MessageCatalogue('en', ['domain1' => ['foo' => 'foo'], 'domain1+intl-icu' => ['bar' => 'bar']]);
        $catalogue->replace($messages = ['foo1' => 'foo1'], 'domain1');

        self::assertEquals($messages, $catalogue->all('domain1'));
    }

    public function testAddCatalogue()
    {
        $r = self::createMock(ResourceInterface::class);
        $r->expects(self::any())->method('__toString')->willReturn('r');

        $r1 = self::createMock(ResourceInterface::class);
        $r1->expects(self::any())->method('__toString')->willReturn('r1');

        $catalogue = new MessageCatalogue('en', ['domain1' => ['foo' => 'foo']]);
        $catalogue->addResource($r);

        $catalogue1 = new MessageCatalogue('en', ['domain1' => ['foo1' => 'foo1'], 'domain2+intl-icu' => ['bar' => 'bar']]);
        $catalogue1->addResource($r1);

        $catalogue->addCatalogue($catalogue1);

        self::assertEquals('foo', $catalogue->get('foo', 'domain1'));
        self::assertEquals('foo1', $catalogue->get('foo1', 'domain1'));
        self::assertEquals('bar', $catalogue->get('bar', 'domain2'));
        self::assertEquals('bar', $catalogue->get('bar', 'domain2+intl-icu'));

        self::assertEquals([$r, $r1], $catalogue->getResources());
    }

    public function testAddFallbackCatalogue()
    {
        $r = self::createMock(ResourceInterface::class);
        $r->expects(self::any())->method('__toString')->willReturn('r');

        $r1 = self::createMock(ResourceInterface::class);
        $r1->expects(self::any())->method('__toString')->willReturn('r1');

        $r2 = self::createMock(ResourceInterface::class);
        $r2->expects(self::any())->method('__toString')->willReturn('r2');

        $catalogue = new MessageCatalogue('fr_FR', ['domain1' => ['foo' => 'foo'], 'domain2' => ['bar' => 'bar']]);
        $catalogue->addResource($r);

        $catalogue1 = new MessageCatalogue('fr', ['domain1' => ['foo' => 'bar', 'foo1' => 'foo1']]);
        $catalogue1->addResource($r1);

        $catalogue2 = new MessageCatalogue('en');
        $catalogue2->addResource($r2);

        $catalogue->addFallbackCatalogue($catalogue1);
        $catalogue1->addFallbackCatalogue($catalogue2);

        self::assertEquals('foo', $catalogue->get('foo', 'domain1'));
        self::assertEquals('foo1', $catalogue->get('foo1', 'domain1'));

        self::assertEquals([$r, $r1, $r2], $catalogue->getResources());
    }

    public function testAddFallbackCatalogueWithParentCircularReference()
    {
        self::expectException(LogicException::class);
        $main = new MessageCatalogue('en_US');
        $fallback = new MessageCatalogue('fr_FR');

        $fallback->addFallbackCatalogue($main);
        $main->addFallbackCatalogue($fallback);
    }

    public function testAddFallbackCatalogueWithFallbackCircularReference()
    {
        self::expectException(LogicException::class);
        $fr = new MessageCatalogue('fr');
        $en = new MessageCatalogue('en');
        $es = new MessageCatalogue('es');

        $fr->addFallbackCatalogue($en);
        $es->addFallbackCatalogue($en);
        $en->addFallbackCatalogue($fr);
    }

    public function testAddCatalogueWhenLocaleIsNotTheSameAsTheCurrentOne()
    {
        self::expectException(LogicException::class);
        $catalogue = new MessageCatalogue('en');
        $catalogue->addCatalogue(new MessageCatalogue('fr', []));
    }

    public function testGetAddResource()
    {
        $catalogue = new MessageCatalogue('en');
        $r = self::createMock(ResourceInterface::class);
        $r->expects(self::any())->method('__toString')->willReturn('r');
        $catalogue->addResource($r);
        $catalogue->addResource($r);
        $r1 = self::createMock(ResourceInterface::class);
        $r1->expects(self::any())->method('__toString')->willReturn('r1');
        $catalogue->addResource($r1);

        self::assertEquals([$r, $r1], $catalogue->getResources());
    }

    public function testMetadataDelete()
    {
        $catalogue = new MessageCatalogue('en');
        self::assertEquals([], $catalogue->getMetadata('', ''), 'Metadata is empty');
        $catalogue->deleteMetadata('key', 'messages');
        $catalogue->deleteMetadata('', 'messages');
        $catalogue->deleteMetadata();
    }

    public function testMetadataSetGetDelete()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->setMetadata('key', 'value');
        self::assertEquals('value', $catalogue->getMetadata('key', 'messages'), "Metadata 'key' = 'value'");

        $catalogue->setMetadata('key2', []);
        self::assertEquals([], $catalogue->getMetadata('key2', 'messages'), 'Metadata key2 is array');

        $catalogue->deleteMetadata('key2', 'messages');
        self::assertNull($catalogue->getMetadata('key2', 'messages'), 'Metadata key2 should is deleted.');

        $catalogue->deleteMetadata('key2', 'domain');
        self::assertNull($catalogue->getMetadata('key2', 'domain'), 'Metadata key2 should is deleted.');
    }

    public function testMetadataMerge()
    {
        $cat1 = new MessageCatalogue('en');
        $cat1->setMetadata('a', 'b');
        self::assertEquals(['messages' => ['a' => 'b']], $cat1->getMetadata('', ''), 'Cat1 contains messages metadata.');

        $cat2 = new MessageCatalogue('en');
        $cat2->setMetadata('b', 'c', 'domain');
        self::assertEquals(['domain' => ['b' => 'c']], $cat2->getMetadata('', ''), 'Cat2 contains domain metadata.');

        $cat1->addCatalogue($cat2);
        self::assertEquals(['messages' => ['a' => 'b'], 'domain' => ['b' => 'c']], $cat1->getMetadata('', ''), 'Cat1 contains merged metadata.');
    }
}
