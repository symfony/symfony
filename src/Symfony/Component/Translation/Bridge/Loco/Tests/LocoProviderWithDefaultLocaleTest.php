<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Loco\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bridge\PhpUnit\ExpectUserDeprecationMessageTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Translation\Bridge\Loco\LocoProvider;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Group('legacy')]
#[IgnoreDeprecations]
class LocoProviderWithDefaultLocaleTest extends LocoProviderTest
{
    use ExpectUserDeprecationMessageTrait;

    public static function createProvider(HttpClientInterface $client, LoaderInterface $loader, LoggerInterface $logger, string $defaultLocale, string $endpoint, ?TranslatorBagInterface $translatorBag = null, ?string $restrictToStatus = null, XliffFileDumper $dumper = new XliffFileDumper()): ProviderInterface
    {
        return new LocoProvider($client, $loader, $logger, $defaultLocale, $endpoint, $translatorBag ?? new TranslatorBag(), $restrictToStatus, $dumper);
    }

    public function testTrailingArgumentsCanBeOmitted()
    {
        $this->expectUserDeprecationMessage('Since symfony/loco-translation-provider 8.2: "Symfony\Component\Translation\Bridge\Loco\LocoProvider" constructor "$defaultLocale" parameter has no effect and will be removed in version 9.0.');

        $provider = new LocoProvider(new MockHttpClient(), new ArrayLoader(), new NullLogger(), 'en', 'localise.biz/api/');

        $this->assertSame('loco://localise.biz/api/', (string) $provider);
    }
}
