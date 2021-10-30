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

use Symfony\Component\Translation\Catalogue\TargetOperation;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;

class TargetOperationTest extends AbstractOperationTest
{
    public function testGetMessagesFromSingleDomain()
    {
        $operation = $this->createOperation(
            new MessageCatalogue('en', ['messages' => ['a' => 'old_a', 'b' => 'old_b']]),
            new MessageCatalogue('en', ['messages' => ['a' => 'new_a', 'c' => 'new_c']])
        );

        $this->assertEquals(
            ['a' => 'old_a', 'c' => 'new_c'],
            $operation->getMessages('messages')
        );

        $this->assertEquals(
            ['c' => 'new_c'],
            $operation->getNewMessages('messages')
        );

        $this->assertEquals(
            ['b' => 'old_b'],
            $operation->getObsoleteMessages('messages')
        );
    }

    public function testGetResultFromSingleDomain()
    {
        $this->assertEquals(
            new MessageCatalogue('en', [
                'messages' => ['a' => 'old_a', 'c' => 'new_c'],
            ]),
            $this->createOperation(
                new MessageCatalogue('en', ['messages' => ['a' => 'old_a', 'b' => 'old_b']]),
                new MessageCatalogue('en', ['messages' => ['a' => 'new_a', 'c' => 'new_c']])
            )->getResult()
        );
    }

    public function testGetResultFromIntlDomain()
    {
        $this->assertEquals(
            new MessageCatalogue('en', [
                'messages' => ['a' => 'old_a'],
                'messages+intl-icu' => ['c' => 'new_c'],
            ]),
            $this->createOperation(
                new MessageCatalogue('en', ['messages' => ['a' => 'old_a'], 'messages+intl-icu' => ['b' => 'old_b']]),
                new MessageCatalogue('en', ['messages' => ['a' => 'new_a'], 'messages+intl-icu' => ['c' => 'new_c']])
            )->getResult()
        );
    }

    public function testGetResultWithMixedDomains()
    {
        $this->assertEquals(
            new MessageCatalogue('en', [
                'messages' => ['a' => 'old_a'],
            ]),
            $this->createOperation(
                new MessageCatalogue('en', ['messages' => ['a' => 'old_a']]),
                new MessageCatalogue('en', ['messages+intl-icu' => ['a' => 'new_a']])
            )->getResult()
        );

        $this->assertEquals(
            new MessageCatalogue('en', [
                'messages+intl-icu' => ['a' => 'old_a'],
            ]),
            $this->createOperation(
                new MessageCatalogue('en', ['messages+intl-icu' => ['a' => 'old_a']]),
                new MessageCatalogue('en', ['messages' => ['a' => 'new_a']])
            )->getResult()
        );

        $this->assertEquals(
            new MessageCatalogue('en', [
                'messages+intl-icu' => ['a' => 'old_a'],
                'messages' => ['b' => 'new_b'],
            ]),
            $this->createOperation(
                new MessageCatalogue('en', ['messages+intl-icu' => ['a' => 'old_a']]),
                new MessageCatalogue('en', ['messages' => ['a' => 'new_a', 'b' => 'new_b']])
            )->getResult()
        );

        $this->assertEquals(
            new MessageCatalogue('en', [
                'messages' => ['a' => 'old_a'],
                'messages+intl-icu' => ['b' => 'new_b'],
            ]),
            $this->createOperation(
                new MessageCatalogue('en', ['messages' => ['a' => 'old_a']]),
                new MessageCatalogue('en', ['messages+intl-icu' => ['a' => 'new_a', 'b' => 'new_b']])
            )->getResult()
        );
    }

    public function testGetResultWithMetadata()
    {
        $leftCatalogue = new MessageCatalogue('en', ['messages' => ['a' => 'old_a', 'b' => 'old_b']]);
        $leftCatalogue->setMetadata('a', 'foo', 'messages');
        $leftCatalogue->setMetadata('b', 'bar', 'messages');
        $rightCatalogue = new MessageCatalogue('en', ['messages' => ['b' => 'new_b', 'c' => 'new_c']]);
        $rightCatalogue->setMetadata('b', 'baz', 'messages');
        $rightCatalogue->setMetadata('c', 'qux', 'messages');

        $diffCatalogue = new MessageCatalogue('en', ['messages' => ['b' => 'old_b', 'c' => 'new_c']]);
        $diffCatalogue->setMetadata('b', 'bar', 'messages');
        $diffCatalogue->setMetadata('c', 'qux', 'messages');

        $this->assertEquals(
            $diffCatalogue,
            $this->createOperation(
                $leftCatalogue,
                $rightCatalogue
            )->getResult()
        );
    }

    public function testGetResultWithMetadataFromIntlDomain()
    {
        $leftCatalogue = new MessageCatalogue('en', ['messages+intl-icu' => ['a' => 'old_a', 'b' => 'old_b']]);
        $leftCatalogue->setMetadata('a', 'foo', 'messages+intl-icu');
        $leftCatalogue->setMetadata('b', 'bar', 'messages+intl-icu');
        $rightCatalogue = new MessageCatalogue('en', ['messages+intl-icu' => ['b' => 'new_b', 'c' => 'new_c']]);
        $rightCatalogue->setMetadata('b', 'baz', 'messages+intl-icu');
        $rightCatalogue->setMetadata('c', 'qux', 'messages+intl-icu');

        $diffCatalogue = new MessageCatalogue('en', ['messages+intl-icu' => ['b' => 'old_b', 'c' => 'new_c']]);
        $diffCatalogue->setMetadata('b', 'bar', 'messages+intl-icu');
        $diffCatalogue->setMetadata('c', 'qux', 'messages+intl-icu');

        $this->assertEquals(
            $diffCatalogue,
            $this->createOperation(
                $leftCatalogue,
                $rightCatalogue
            )->getResult()
        );
    }

    protected function createOperation(MessageCatalogueInterface $source, MessageCatalogueInterface $target)
    {
        return new TargetOperation($source, $target);
    }
}
