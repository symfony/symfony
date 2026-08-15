<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Scaleway\Tests\Webhook;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Checks that the bundled Scaleway CA trust chain is in sync with the official one.
 *
 * @see https://www.scaleway.com/en/docs/topics-and-events/reference-content/verifying-webhooks/
 */
#[Group('network')]
class ScalewayTrustChainTest extends TestCase
{
    #[DataProvider('provideRegions')]
    public function testTrustChainIsUpToDate(string $region)
    {
        if (false === $official = @file_get_contents(\sprintf('https://messaging.s3.fr-par.scw.cloud/%s/sns/sns-trust-chain.pem', $region))) {
            $this->markTestSkipped('Unable to fetch the official Scaleway trust chain.');
        }

        $this->assertSame($official, file_get_contents(__DIR__.'/../../Resources/sns-trust-chain.pem'), \sprintf('The bundled Scaleway CA trust chain is out of sync with the official one for the "%s" region.', $region));
    }

    public static function provideRegions(): iterable
    {
        yield 'fr-par' => ['fr-par'];
        yield 'nl-ams' => ['nl-ams'];
    }
}
