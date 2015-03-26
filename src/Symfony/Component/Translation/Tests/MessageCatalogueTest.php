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

use Symfony\Component\Translation\MessageCatalogue;

class MessageCatalogueTest extends \PHPUnit_Framework_TestCase
{
    public function testGetLocale()
    {
        $catalogue = $this->getCatalogue('en');

        $this->assertEquals('en', $catalogue->getLocale());
    }

    public function testGetDomains()
    {
        $catalogue = $this->getCatalogue('en', array('domain1' => array(), 'domain2' => array()));

        $this->assertEquals(array('domain1', 'domain2'), $catalogue->getDomains());
    }

    public function testAll()
    {
        $catalogue = $this->getCatalogue('en', $messages = array('domain1' => array('foo' => 'foo'), 'domain2' => array('bar' => 'bar')));

        $this->assertEquals(array('foo' => 'foo'), $catalogue->all('domain1'));
        $this->assertEquals(array(), $catalogue->all('domain88'));
        $this->assertEquals($messages, $catalogue->all());
    }

    public function testHas()
    {
        $catalogue = $this->getCatalogue('en', array('domain1' => array('foo' => 'foo'), 'domain2' => array('bar' => 'bar')));

        $this->assertTrue($catalogue->has('foo', 'domain1'));
        $this->assertFalse($catalogue->has('bar', 'domain1'));
        $this->assertFalse($catalogue->has('foo', 'domain88'));
    }

    public function testGetSet()
    {
        $catalogue = $this->getCatalogue('en', array('domain1' => array('foo' => 'foo'), 'domain2' => array('bar' => 'bar')));
        $catalogue->set('foo1', 'foo1', 'domain1');

        $this->assertEquals('foo', $catalogue->get('foo', 'domain1'));
        $this->assertEquals('foo1', $catalogue->get('foo1', 'domain1'));
    }

    public function testAdd()
    {
        $catalogue = $this->getCatalogue('en', array('domain1' => array('foo' => 'foo'), 'domain2' => array('bar' => 'bar')));
        $catalogue->add(array('foo1' => 'foo1'), 'domain1');

        $this->assertEquals('foo', $catalogue->get('foo', 'domain1'));
        $this->assertEquals('foo1', $catalogue->get('foo1', 'domain1'));

        $catalogue->add(array('foo' => 'bar'), 'domain1');
        $this->assertEquals('bar', $catalogue->get('foo', 'domain1'));
        $this->assertEquals('foo1', $catalogue->get('foo1', 'domain1'));

        $catalogue->add(array('foo' => 'bar'), 'domain88');
        $this->assertEquals('bar', $catalogue->get('foo', 'domain88'));
    }

    public function testReplace()
    {
        $catalogue = $this->getCatalogue('en', array('domain1' => array('foo' => 'foo'), 'domain2' => array('bar' => 'bar')));
        $catalogue->replace($messages = array('foo1' => 'foo1'), 'domain1');

        $this->assertEquals($messages, $catalogue->all('domain1'));
    }

    public function testAddCatalogue()
    {
        $r = $this->getMock('Symfony\Component\Config\Resource\ResourceInterface');
        $r->expects($this->any())->method('__toString')->will($this->returnValue('r'));

        $r1 = $this->getMock('Symfony\Component\Config\Resource\ResourceInterface');
        $r1->expects($this->any())->method('__toString')->will($this->returnValue('r1'));

        $catalogue = $this->getCatalogue('en', array('domain1' => array('foo' => 'foo'), 'domain2' => array('bar' => 'bar')));
        $catalogue->addResource($r);

        $catalogue1 = $this->getCatalogue('en', array('domain1' => array('foo1' => 'foo1')));
        $catalogue1->addResource($r1);

        $catalogue->addCatalogue($catalogue1);

        $this->assertEquals('foo', $catalogue->get('foo', 'domain1'));
        $this->assertEquals('foo1', $catalogue->get('foo1', 'domain1'));

        $this->assertEquals(array($r, $r1), $catalogue->getResources());
    }

    public function testAddFallbackCatalogue()
    {
        $r = $this->getMock('Symfony\Component\Config\Resource\ResourceInterface');
        $r->expects($this->any())->method('__toString')->will($this->returnValue('r'));

        $r1 = $this->getMock('Symfony\Component\Config\Resource\ResourceInterface');
        $r1->expects($this->any())->method('__toString')->will($this->returnValue('r1'));

        $catalogue = $this->getCatalogue('en_US', array('domain1' => array('foo' => 'foo'), 'domain2' => array('bar' => 'bar')));
        $catalogue->addResource($r);

        $catalogue1 = $this->getCatalogue('en', array('domain1' => array('foo' => 'bar', 'foo1' => 'foo1')));
        $catalogue1->addResource($r1);

        $catalogue->addFallbackCatalogue($catalogue1);

        $this->assertEquals('foo', $catalogue->get('foo', 'domain1'));
        $this->assertEquals('foo1', $catalogue->get('foo1', 'domain1'));

        $this->assertEquals(array($r, $r1), $catalogue->getResources());
    }

    /**
     * @expectedException \LogicException
     */
    public function testAddFallbackCatalogueWithCircularReference()
    {
        $main = $this->getCatalogue('en_US');
        $fallback = $this->getCatalogue('fr_FR');

        $fallback->addFallbackCatalogue($main);
        $main->addFallbackCatalogue($fallback);
    }

    /**
     * @expectedException \LogicException
     */
    public function testAddCatalogueWhenLocaleIsNotTheSameAsTheCurrentOne()
    {
        $catalogue = $this->getCatalogue('en');
        $catalogue->addCatalogue($this->getCatalogue('fr', array()));
    }

    public function testGetAddResource()
    {
        $catalogue = $this->getCatalogue('en');
        $r = $this->getMock('Symfony\Component\Config\Resource\ResourceInterface');
        $r->expects($this->any())->method('__toString')->will($this->returnValue('r'));
        $catalogue->addResource($r);
        $catalogue->addResource($r);
        $r1 = $this->getMock('Symfony\Component\Config\Resource\ResourceInterface');
        $r1->expects($this->any())->method('__toString')->will($this->returnValue('r1'));
        $catalogue->addResource($r1);

        $this->assertEquals(array($r, $r1), $catalogue->getResources());
    }

    public function testMetadataDelete()
    {
        $catalogue = $this->getCatalogue('en');
        $this->assertEquals(array(), $catalogue->getMetadata('', ''), 'Metadata is empty');
        $catalogue->deleteMetadata('key', 'messages');
        $catalogue->deleteMetadata('', 'messages');
        $catalogue->deleteMetadata();
    }

    public function testMetadataSetGetDelete()
    {
        $catalogue = $this->getCatalogue('en');
        $catalogue->setMetadata('key', 'value');
        $this->assertEquals('value', $catalogue->getMetadata('key', 'messages'), "Metadata 'key' = 'value'");

        $catalogue->setMetadata('key2', array());
        $this->assertEquals(array(), $catalogue->getMetadata('key2', 'messages'), 'Metadata key2 is array');

        $catalogue->deleteMetadata('key2', 'messages');
        $this->assertEquals(null, $catalogue->getMetadata('key2', 'messages'), 'Metadata key2 should is deleted.');

        $catalogue->deleteMetadata('key2', 'domain');
        $this->assertEquals(null, $catalogue->getMetadata('key2', 'domain'), 'Metadata key2 should is deleted.');
    }

    public function testMetadataMerge()
    {
        $cat1 = $this->getCatalogue('en');
        $cat1->setMetadata('a', 'b');
        $this->assertEquals(array('messages' => array('a' => 'b')), $cat1->getMetadata('', ''), 'Cat1 contains messages metadata.');

        $cat2 = $this->getCatalogue('en');
        $cat2->setMetadata('b', 'c', 'domain');
        $this->assertEquals(array('domain' => array('b' => 'c')), $cat2->getMetadata('', ''), 'Cat2 contains domain metadata.');

        $cat1->addCatalogue($cat2);
        $this->assertEquals(array('messages' => array('a' => 'b'), 'domain' => array('b' => 'c')), $cat1->getMetadata('', ''), 'Cat1 contains merged metadata.');
    }

    protected function getCatalogue($locale, $messages = array())
    {
        return new MessageCatalogue($locale, $messages);
    }
}
