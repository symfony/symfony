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

use Symfony\Component\Translation\Catalogue\ReplaceOperation;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;

class ReplaceOperationTest extends MergeOperationTest
{
    public function testGetMessagesFromSingleDomain()
    {
        $operation = $this->createOperation(
            new MessageCatalogue('en', array('messages' => array('a' => 'new_a', 'c' => 'new_c'))),
            new MessageCatalogue('en', array('messages' => array('a' => 'old_a', 'b' => 'old_b')))
        );

        $this->assertEquals(
            array('a' => 'new_a', 'b' => 'old_b', 'c' => 'new_c'),
            $operation->getMessages('messages')
        );

        $this->assertEquals(
            array('c' => 'new_c'),
            $operation->getNewMessages('messages')
        );

        $this->assertEquals(
            array('b' => 'old_b'),
            $operation->getObsoleteMessages('messages')
        );
    }

    public function testGetResultFromSingleDomain()
    {
        $this->assertEquals(
            new MessageCatalogue('en', array(
                'messages' => array('a' => 'new_a', 'b' => 'old_b', 'c' => 'new_c'),
            )),
            $this->createOperation(
                new MessageCatalogue('en', array('messages' => array('a' => 'new_a', 'c' => 'new_c'))),
                new MessageCatalogue('en', array('messages' => array('a' => 'old_a', 'b' => 'old_b')))
            )->getResult()
        );
    }

    public function testGetResultWithMetadata()
    {
        $leftCatalogue = new MessageCatalogue('en', array('messages' => array('a' => 'new_a', 'b' => 'new_b')));
        $leftCatalogue->setMetadata('a', 'foo', 'messages');
        $leftCatalogue->setMetadata('b', 'bar', 'messages');
        $rightCatalogue = new MessageCatalogue('en', array('messages' => array('b' => 'old_b', 'c' => 'old_c')));
        $rightCatalogue->setMetadata('b', 'baz', 'messages');
        $rightCatalogue->setMetadata('c', 'qux', 'messages');

        $mergedCatalogue = new MessageCatalogue('en', array('messages' => array('a' => 'new_a', 'b' => 'new_b', 'c' => 'old_c')));
        $mergedCatalogue->setMetadata('a', 'foo', 'messages');
        $mergedCatalogue->setMetadata('b', 'bar', 'messages');
        $mergedCatalogue->setMetadata('c', 'qux', 'messages');

        $this->assertEquals(
            $mergedCatalogue,
            $this->createOperation($leftCatalogue, $rightCatalogue)->getResult()
        );
    }

    public function testGetResultWithArrayMetadata()
    {
        $leftCatalogue = new MessageCatalogue('en', array('messages' => array('a' => 'new_a', 'b' => 'new_b')));
        $notes = array(
            array('category' => 'note1', 'content' => 'a'),
            array('category' => 'note2', 'content' => 'b'),
        );
        $leftCatalogue->setMetadata('a', array('notes' => array('test')), 'messages');
        $leftCatalogue->setMetadata('b', array('notes' => $notes, 'meta0' => 'zz', 'meta1' => 'yy'), 'messages');

        $rightCatalogue = new MessageCatalogue('en', array('messages' => array('b' => 'old_b', 'c' => 'old_c')));
        $notes = array(
            array('category' => 'note2', 'content' => 'b'),
            array('category' => 'note2', 'content' => 'c'),
        );
        $rightCatalogue->setMetadata('b', array('notes' => $notes, 'meta0' => 'aa', 'meta2' => 'xx'), 'messages');
        $rightCatalogue->setMetadata('c', 'qux', 'messages');

        $mergedCatalogue = new MessageCatalogue('en', array('messages' => array('a' => 'new_a', 'b' => 'new_b', 'c' => 'old_c')));
        $mergedNotes = array(
            array('category' => 'note1', 'content' => 'a'),
            array('category' => 'note2', 'content' => 'b'),
            array('category' => 'note2', 'content' => 'c'),
        );
        $mergedCatalogue->setMetadata('a', array('notes' => array('test')), 'messages');
        $mergedCatalogue->setMetadata('b', array('notes' => $mergedNotes, 'meta0' => 'zz',  'meta1' => 'yy', 'meta2' => 'xx'), 'messages');
        $mergedCatalogue->setMetadata('c', 'qux', 'messages');

        $resultCatalogue = $this->createOperation($leftCatalogue, $rightCatalogue)->getResult();

        $this->assertEquals(array('notes' => array('test')), $resultCatalogue->getMetadata('a'));
        $this->assertEquals('qux', $resultCatalogue->getMetadata('c'));

        $bMeta = $resultCatalogue->getMetadata('b');
        $this->assertCount(4, $bMeta);
        $this->assertEquals('zz', $bMeta['meta0']);
        $this->assertEquals('yy', $bMeta['meta1']);
        $this->assertEquals('xx', $bMeta['meta2']);
        $this->assertCount(3, $bMeta['notes']);
        foreach ($mergedNotes as $note) {
            $this->assertContains($note, $bMeta['notes']);
        }
    }

    public function testGetResultWithOneCatalogeWithoutMetadata()
    {
        $a = new MessageCatalogue('en', array('messages' => array('foo' => 'foo_a')));
        $notes = array(
            array('category' => 'note1', 'content' => 'a'),
        );
        // Only $a has metadata
        $a->setMetadata('foo', array('notes' => $notes), 'messages');

        $b = new MessageCatalogue('en', array('messages' => array('foo' => 'foo_b')));

        $mergedCatalogue = new MessageCatalogue('en', array('messages' => array('foo' => 'foo_a')));
        $mergedCatalogue->setMetadata('foo', array('notes' => $notes), 'messages');

        $resultCatalogue = $this->createOperation($a, $b)->getResult();
        $this->assertEquals($mergedCatalogue, $resultCatalogue);

        // Reverse the operation
        $mergedCatalogue = new MessageCatalogue('en', array('messages' => array('foo' => 'foo_b')));
        $mergedCatalogue->setMetadata('foo', array('notes' => $notes), 'messages');

        $resultCatalogue = $this->createOperation($b, $a)->getResult();
        $this->assertEquals($mergedCatalogue, $resultCatalogue);
    }

    protected function createOperation(MessageCatalogueInterface $source, MessageCatalogueInterface $target)
    {
        return new ReplaceOperation($source, $target);
    }
}
