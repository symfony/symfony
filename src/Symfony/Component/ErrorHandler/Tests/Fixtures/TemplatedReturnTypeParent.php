<?php

namespace Test\Symfony\Component\ErrorHandler\Tests;

use Symfony\Component\ErrorHandler\Tests\Fixtures\ReturnTypeGrandParent;

/**
 * @template T
 */
abstract class TemplatedReturnTypeParent extends ReturnTypeGrandParent implements \Iterator
{
    /**
     * @return T
     */
    #[\ReturnTypeWillChange]
    public function current() { }

    /**
     * @return T
     */
    public function returnTypeGrandParent() { }

    /**
     * @return T
     */
    public function returnTypeParentInterface() { }

    public function key(): mixed { }
    public function next(): void { }
    public function rewind(): void { }
    public function valid(): bool { }
}
