<?php

namespace Symfony\Bridge\PhpUnit\Tests\DeprecationErrorHandler;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler\DeprecationGroup;

final class DeprecationGroupTest extends TestCase
{
    public function testItGroupsByMessage()
    {
        $group = new DeprecationGroup();
        $group->addNoticeFromObject(
            'Calling sfContext::getInstance() is deprecated',
            'MonsterController',
            'get5klocMethod'
        );
        $group->addNoticeFromProceduralCode('Calling sfContext::getInstance() is deprecated');
        $this->assertCount(1, $group->notices());
        $this->assertSame(2, $group->count());
    }

    public function testItAllowsAddingANoticeWithoutClutteringTheMemory()
    {
        // this is useful for notices in the legacy group
        $group = new DeprecationGroup();
        $group->addNotice();
        $this->assertSame(1, $group->count());
    }
}
