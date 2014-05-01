<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Tests\Adapter;

use Symfony\Component\Finder\Adapter\AdapterInterface;
use Symfony\Component\Finder\Adapter\AdapterCollection;
use Symfony\Component\Finder\Tests\FakeAdapter;

class AdapterCollectionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var AdapterCollection
     */
    private $collection;

    protected function setUp()
    {
        $this->collection = new AdapterCollection();

        $this
            ->collection
            ->add(new FakeAdapter\NamedAdapter('a'), 0)
            ->add(new FakeAdapter\NamedAdapter('b'), -50)
            ->add(new FakeAdapter\NamedAdapter('c'), 50)
            ->add(new FakeAdapter\NamedAdapter('d'), -25)
            ->add(new FakeAdapter\NamedAdapter('e'), 25)
        ;
    }

    protected function tearDown()
    {
        $this->collection = null;
    }

    public function testUseBestAdapter()
    {
        $this->collection->useBestAdapter();
        $this->assertSame(
            array('c', 'e', 'a', 'd', 'b'),
            $this->getAdapterNames($this->collection->all())
        );
    }

    public function testSetAdapter()
    {
        $this->collection->setAdapter('b');
        $this->assertSame(
            array('b', 'c', 'e', 'a', 'd'),
            $this->getAdapterNames($this->collection->all())
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testAdapterNotFound()
    {
        $this->collection->setAdapter('php');
    }

    public function testGetAllAdapters()
    {
        $this->assertCount(5, $this->collection->all());
        $this->assertSame(
            array('c', 'e', 'a', 'd', 'b'),
            $this->getAdapterNames($this->collection->all())
        );
    }

    public function testClearCollection()
    {
        $this->collection->clear();
        $this->assertEmpty($this->collection->all());
    }

    public function testAdaptersOrdering()
    {
        $this->assertSame(
            array('c', 'e', 'a', 'd', 'b'),
            $this->getAdapterNames($this->collection->all())
        );
    }

    private function getAdapterNames(array $adapters)
    {
        return array_map(
            function (AdapterInterface $adapter) {
                return $adapter->getName();
            },
            $adapters
        );
    }
}
