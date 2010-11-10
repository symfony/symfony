<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Translation;

use Symfony\Component\Translation\MessageCatalogue;

class MessageCatalogueTest extends \PHPUnit_Framework_TestCase
{
    public function testGetLocale()
    {
        $catalogue = new MessageCatalogue('en');

        $this->assertEquals('en', $catalogue->getLocale());
    }

    public function testGetDomains()
    {
        $catalogue = new MessageCatalogue('en', array('domain1' => array(), 'domain2' => array()));

        $this->assertEquals(array('domain1', 'domain2'), $catalogue->getDomains());
    }

    public function testGetMessages()
    {
        $catalogue = new MessageCatalogue('en', $messages = array('domain1' => array('foo' => 'foo'), 'domain2' => array('bar' => 'bar')));

        $this->assertEquals(array('foo' => 'foo'), $catalogue->getMessages('domain1'));
        $this->assertEquals(array(), $catalogue->getMessages('domain88'));
        $this->assertEquals($messages, $catalogue->getMessages());
    }

    public function testHasMessage()
    {
        $catalogue = new MessageCatalogue('en', array('domain1' => array('foo' => 'foo'), 'domain2' => array('bar' => 'bar')));

        $this->assertTrue($catalogue->hasMessage('foo', 'domain1'));
        $this->assertFalse($catalogue->hasMessage('bar', 'domain1'));
        $this->assertFalse($catalogue->hasMessage('foo', 'domain88'));
    }

    public function testGetSetMessage()
    {
        $catalogue = new MessageCatalogue('en', array('domain1' => array('foo' => 'foo'), 'domain2' => array('bar' => 'bar')));
        $catalogue->setMessage('foo1', 'foo1', 'domain1');

        $this->assertEquals('foo', $catalogue->getMessage('foo', 'domain1'));
        $this->assertEquals('foo1', $catalogue->getMessage('foo1', 'domain1'));
    }

    public function testAddMessages()
    {
        $catalogue = new MessageCatalogue('en', array('domain1' => array('foo' => 'foo'), 'domain2' => array('bar' => 'bar')));
        $catalogue->addMessages(array('foo1' => 'foo1'), 'domain1');

        $this->assertEquals('foo', $catalogue->getMessage('foo', 'domain1'));
        $this->assertEquals('foo1', $catalogue->getMessage('foo1', 'domain1'));

        $catalogue->addMessages(array('foo' => 'bar'), 'domain1');
        $this->assertEquals('bar', $catalogue->getMessage('foo', 'domain1'));
        $this->assertEquals('foo1', $catalogue->getMessage('foo1', 'domain1'));

        $catalogue->addMessages(array('foo' => 'bar'), 'domain88');
        $this->assertEquals('bar', $catalogue->getMessage('foo', 'domain88'));
    }

    public function testSetMessages()
    {
        $catalogue = new MessageCatalogue('en', array('domain1' => array('foo' => 'foo'), 'domain2' => array('bar' => 'bar')));
        $catalogue->setMessages($messages = array('foo1' => 'foo1'), 'domain1');

        $this->assertEquals($messages, $catalogue->getMessages('domain1'));
    }

    public function testAddCatalogue()
    {
        $r = $this->getMock('Symfony\Component\Translation\Resource\ResourceInterface');
        $r->expects($this->any())->method('__toString')->will($this->returnValue('r'));

        $r1 = $this->getMock('Symfony\Component\Translation\Resource\ResourceInterface');
        $r1->expects($this->any())->method('__toString')->will($this->returnValue('r1'));

        $catalogue = new MessageCatalogue('en', array('domain1' => array('foo' => 'foo'), 'domain2' => array('bar' => 'bar')));
        $catalogue->addResource($r);

        $catalogue1 = new MessageCatalogue('en', array('domain1' => array('foo1' => 'foo1')));
        $catalogue1->addResource($r1);

        $catalogue->addCatalogue($catalogue1);

        $this->assertEquals('foo', $catalogue->getMessage('foo', 'domain1'));
        $this->assertEquals('foo1', $catalogue->getMessage('foo1', 'domain1'));

        $this->assertEquals(array($r, $r1), $catalogue->getResources());
    }

    public function testAddFallbackCatalogue()
    {
        $r = $this->getMock('Symfony\Component\Translation\Resource\ResourceInterface');
        $r->expects($this->any())->method('__toString')->will($this->returnValue('r'));

        $r1 = $this->getMock('Symfony\Component\Translation\Resource\ResourceInterface');
        $r1->expects($this->any())->method('__toString')->will($this->returnValue('r1'));

        $catalogue = new MessageCatalogue('en_US', array('domain1' => array('foo' => 'foo'), 'domain2' => array('bar' => 'bar')));
        $catalogue->addResource($r);

        $catalogue1 = new MessageCatalogue('en', array('domain1' => array('foo' => 'bar', 'foo1' => 'foo1')));
        $catalogue1->addResource($r1);

        $catalogue->addFallbackCatalogue($catalogue1);

        $this->assertEquals('foo', $catalogue->getMessage('foo', 'domain1'));
        $this->assertEquals('foo1', $catalogue->getMessage('foo1', 'domain1'));

        $this->assertEquals(array($r, $r1), $catalogue->getResources());
    }

    /**
     * @expectedException LogicException
     */
    public function testAddCatalogueWhenLocaleIsNotTheSameAsTheCurrentOne()
    {
        $catalogue = new MessageCatalogue('en');
        $catalogue->addCatalogue(new MessageCatalogue('fr', array()));
    }

    public function testGetAddResource()
    {
        $catalogue = new MessageCatalogue('en');
        $r = $this->getMock('Symfony\Component\Translation\Resource\ResourceInterface');
        $r->expects($this->any())->method('__toString')->will($this->returnValue('r'));
        $catalogue->addResource($r);
        $catalogue->addResource($r);
        $r1 = $this->getMock('Symfony\Component\Translation\Resource\ResourceInterface');
        $r1->expects($this->any())->method('__toString')->will($this->returnValue('r1'));
        $catalogue->addResource($r1);

        $this->assertEquals(array($r, $r1), $catalogue->getResources());
    }
}
