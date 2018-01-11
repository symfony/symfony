<?php

namespace Symfony\Bridge\PhpUnit\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerTrait;

/**
 * Don't remove this test case, it tests the SymfonyTestsListenerTrait.
 *
 * @group legacy
 */
class ExpectDeprecationTest extends TestCase
{
    public function testExpectDeprecation()
    {
        SymfonyTestsListenerTrait::expectDeprecation('Test abc');
        @trigger_error('Test abc', E_USER_DEPRECATED);
        $this->addToAssertionCount(1);
    }
}
