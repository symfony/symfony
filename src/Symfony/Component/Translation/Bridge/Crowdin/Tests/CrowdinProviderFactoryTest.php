<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Crowdin\Tests;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Translation\Bridge\Crowdin\CrowdinProviderFactory;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\ProviderFactoryInterface;
use Symfony\Component\Translation\Test\AbstractProviderFactoryTestCase;
use Symfony\Component\Translation\Test\IncompleteDsnTestTrait;

class CrowdinProviderFactoryTest extends AbstractProviderFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public static function supportsProvider(): iterable
    {
        yield [true, 'crowdin://PROJECT_ID:API_TOKEN@default'];
        yield [false, 'somethingElse://PROJECT_ID:API_TOKEN@default'];
    }

    public static function createProvider(): iterable
    {
        yield [
            'crowdin://api.crowdin.com',
            'crowdin://PROJECT_ID:API_TOKEN@default',
        ];

        yield [
            'crowdin://ORGANIZATION_DOMAIN.api.crowdin.com',
            'crowdin://PROJECT_ID:API_TOKEN@ORGANIZATION_DOMAIN.default',
        ];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://API_TOKEN@default'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield ['crowdin://default'];
    }

    public function createFactory(): ProviderFactoryInterface
    {
        return new CrowdinProviderFactory(new MockHttpClient(), new NullLogger(), 'en', $this->createMock(LoaderInterface::class), $this->createMock(XliffFileDumper::class));
    }
}
