<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Translation\Tests\Catalogue;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Translation\MessageCatalogue;
use Symphony\Component\Translation\MessageCatalogueInterface;

abstract class AbstractOperationTest extends TestCase
{
    public function testGetEmptyDomains()
    {
        $this->assertEquals(
            array(),
            $this->createOperation(
                new MessageCatalogue('en'),
                new MessageCatalogue('en')
            )->getDomains()
        );
    }

    public function testGetMergedDomains()
    {
        $this->assertEquals(
            array('a', 'b', 'c'),
            $this->createOperation(
                new MessageCatalogue('en', array('a' => array(), 'b' => array())),
                new MessageCatalogue('en', array('b' => array(), 'c' => array()))
            )->getDomains()
        );
    }

    public function testGetMessagesFromUnknownDomain()
    {
        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('InvalidArgumentException');
        $this->createOperation(
            new MessageCatalogue('en'),
            new MessageCatalogue('en')
        )->getMessages('domain');
    }

    public function testGetEmptyMessages()
    {
        $this->assertEquals(
            array(),
            $this->createOperation(
                new MessageCatalogue('en', array('a' => array())),
                new MessageCatalogue('en')
            )->getMessages('a')
        );
    }

    public function testGetEmptyResult()
    {
        $this->assertEquals(
            new MessageCatalogue('en'),
            $this->createOperation(
                new MessageCatalogue('en'),
                new MessageCatalogue('en')
            )->getResult()
        );
    }

    abstract protected function createOperation(MessageCatalogueInterface $source, MessageCatalogueInterface $target);
}
