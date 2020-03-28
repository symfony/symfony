<?php

namespace Symfony\Bridge\PhpUnit\Tests\DeprecationErrorHandler;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler\DeprecationNotice;

final class DeprecationNoticeTest extends TestCase
{
    public function testItGroupsByCaller()
    {
        $notice = new DeprecationNotice();
        $notice->addObjectOccurrence('MyAction', '__invoke');
        $notice->addObjectOccurrence('MyAction', '__invoke');
        $notice->addObjectOccurrence('MyOtherAction', '__invoke');

        $countsByCaller = $notice->getCountsByCaller();

        $this->assertCount(2, $countsByCaller);
        $this->assertArrayHasKey('MyAction::__invoke', $countsByCaller);
        $this->assertArrayHasKey('MyOtherAction::__invoke', $countsByCaller);
        $this->assertSame(2, $countsByCaller['MyAction::__invoke']);
        $this->assertSame(1, $countsByCaller['MyOtherAction::__invoke']);
    }

    public function testItCountsBothTypesOfOccurrences()
    {
        $notice = new DeprecationNotice();
        $notice->addObjectOccurrence('MyAction', '__invoke');
        $this->assertSame(1, $notice->count());

        $notice->addProceduralOccurrence();
        $this->assertSame(2, $notice->count());
    }
}
