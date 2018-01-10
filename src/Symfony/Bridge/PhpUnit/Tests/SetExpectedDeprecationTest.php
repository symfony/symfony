<?php

namespace Symfony\Bridge\PhpUnit\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerTrait;

/**
 * Don't remove this test case, it tests the legacy group.
 *
 * @group legacy
 */
class SetExpectedDeprecationTest extends TestCase
{
    public function testSetExpectedDeprecation()
    {
        SymfonyTestsListenerTrait::setExpectedDeprecation('Test abc');
        @trigger_error('Test abc', E_USER_DEPRECATED);
    }
}
