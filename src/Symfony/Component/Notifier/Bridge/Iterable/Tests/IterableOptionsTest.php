<?php

declare(strict_types=1);

namespace Symfony\Component\Notifier\Bridge\Iterable\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Iterable\IterableOptions;

final class IterableOptionsTest extends TestCase
{
    public function testIterableOptionsWithSetters(): void
    {
        $options = (new IterableOptions())
            ->recipientEmail('test@email.com')
            ->recipientUserId('1234')
            ->dataFields(['field' => 'value'])
            ->sendAt('2021-05-05 01:01:00')
            ->allowRepeatMarketingSends(false)
            ->metadata(['some' => 'data'])
        ;

        $this->assertSame($options->toArray(), [
            'recipientEmail' => 'test@email.com',
            'recipientUserId' => '1234',
            'dataFields' => ['field' => 'value'],
            'sendAt' => '2021-05-05 01:01:00',
            'allowRepeatMarketingSends' => false,
            'metadata' => ['some' => 'data'],
            'campaignId' => null,
        ]);
    }

    public function testIterableOptionsWithConstructorArgs(): void
    {
        $options = new IterableOptions([
            'recipientEmail' => 'test@email.com',
            'recipientUserId' => '1234',
            'dataFields' => ['field' => 'value'],
            'sendAt' => '2021-05-05 01:01:00',
            'allowRepeatMarketingSends' => false,
            'metadata' => ['some' => 'data'],
        ], 12345);

        $this->assertSame($options->toArray(), [
            'recipientEmail' => 'test@email.com',
            'recipientUserId' => '1234',
            'dataFields' => ['field' => 'value'],
            'sendAt' => '2021-05-05 01:01:00',
            'allowRepeatMarketingSends' => false,
            'metadata' => ['some' => 'data'],
            'campaignId' => 12345,
        ]);
    }
}
