<?php

declare(strict_types=1);

namespace Symfony\Component\Notifier\Bridge\Iterable\Tests;

use Symfony\Component\Notifier\Bridge\Iterable\IterableTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

final class IterableTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return IterableTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new IterableTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield ['iterable://host.test', 'iterable://token@host.test'];
        yield ['iterable://host.test?campaign_id=testCampaignId', 'iterable://token@host.test?campaign_id=testCampaignId'];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'iterable://token@host.test'];
        yield [true, 'iterable://token@host.test?campaign_id=testCampaignId'];
        yield [false, 'somethingElse://host.test?campaign_id=testCampaignId'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['iterable://host.test'];
        yield 'missing token with campaignId' => ['iterable://host.test?campaign_id=testCampaignId'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://token@host?campaign_id=testCampaignId'];
        yield ['somethingElse://token@host']; // missing "campaign_id" option
    }
}
