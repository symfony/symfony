<?php

namespace Symfony\Component\Workflow\Tests\MarkingStore;

use Symfony\Component\Workflow\Marking;

abstract class MarkingStoreTest extends \PHPUnit_Framework_TestCase
{
    public function testMarkingHasDefaultStrategy()
    {
        $markingStoreClass = $this->getClass();
        $markingStore = new $markingStoreClass();

        $this->assertSame(Marking::STRATEGY_MULTIPLE_STATE, $markingStore->getStrategy());
    }

    abstract public function getClass();
}
