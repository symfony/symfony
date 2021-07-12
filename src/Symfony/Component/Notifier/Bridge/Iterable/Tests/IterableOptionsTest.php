<?php

declare(strict_types=1);

namespace Symfony\Component\Notifier\Bridge\Iterable\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Iterable\IterableOptions;

final class IterableOptionsTest extends TestCase
{
    public function testIterableOptionsWithSetters()
    {
        $sendAt = new \DateTime('2021-05-05 01:01:00');
        $options = (new IterableOptions())
            ->recipientEmail('test@email.com')
            ->recipientUserId('1234')
            ->dataFields(['field' => 'value'])
            ->sendAt($sendAt)
            ->allowRepeatMarketingSends(false)
            ->metadata(['some' => 'data'])
        ;

        $this->assertSame($options->toArray(), [
            'recipientEmail' => 'test@email.com',
            'recipientUserId' => '1234',
            'dataFields' => ['field' => 'value'],
            'sendAt' => $sendAt->format('Y-m-d H:i:s'),
            'allowRepeatMarketingSends' => false,
            'metadata' => ['some' => 'data'],
            'campaignId' => null,
        ]);
    }

    public function testIterableOptionsWithConstructorArgs()
    {
        $sendAt = new \DateTime('2021-05-05 01:01:00');
        $options = (new IterableOptions(12345))
            ->sendAt($sendAt);

        $this->assertSame($options->toArray(), [
            'sendAt' => $sendAt->format('Y-m-d H:i:s'),
            'campaignId' => 12345,
        ]);
    }
}
