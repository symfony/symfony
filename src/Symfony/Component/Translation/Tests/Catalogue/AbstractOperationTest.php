<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Catalogue;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;

abstract class AbstractOperationTest extends TestCase
{
    public function testGetEmptyDomains()
    {
        self::assertEquals([], $this->createOperation(
            new MessageCatalogue('en'),
            new MessageCatalogue('en')
        )->getDomains());
    }

    public function testGetMergedDomains()
    {
        self::assertEquals(['a', 'b', 'c'], $this->createOperation(
            new MessageCatalogue('en', ['a' => [], 'b' => []]),
            new MessageCatalogue('en', ['b' => [], 'c' => []])
        )->getDomains());
    }

    public function testGetMessagesFromUnknownDomain()
    {
        self::expectException(\InvalidArgumentException::class);
        $this->createOperation(
            new MessageCatalogue('en'),
            new MessageCatalogue('en')
        )->getMessages('domain');
    }

    public function testGetEmptyMessages()
    {
        self::assertEquals([], $this->createOperation(
            new MessageCatalogue('en', ['a' => []]),
            new MessageCatalogue('en')
        )->getMessages('a'));
    }

    public function testGetEmptyResult()
    {
        self::assertEquals(new MessageCatalogue('en'), $this->createOperation(
            new MessageCatalogue('en'),
            new MessageCatalogue('en')
        )->getResult());
    }

    abstract protected function createOperation(MessageCatalogueInterface $source, MessageCatalogueInterface $target);
}
