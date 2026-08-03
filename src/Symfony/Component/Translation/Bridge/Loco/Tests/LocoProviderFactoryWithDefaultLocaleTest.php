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
use Psr\Log\NullLogger;
use Symfony\Bridge\PhpUnit\ExpectUserDeprecationMessageTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Translation\Bridge\Loco\LocoProviderFactory;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\Dsn;
use Symfony\Component\Translation\Provider\ProviderFactoryInterface;
use Symfony\Component\Translation\TranslatorBag;

#[Group('legacy')]
#[IgnoreDeprecations]
class LocoProviderFactoryWithDefaultLocaleTest extends LocoProviderFactoryTest
{
    use ExpectUserDeprecationMessageTrait;

    public function createFactory(): ProviderFactoryInterface
    {
        $this->expectUserDeprecationMessage('Since symfony/loco-translation-provider 8.2: "Symfony\Component\Translation\Bridge\Loco\LocoProviderFactory" constructor "$defaultLocale" parameter has no effect and will be removed in version 9.0.');

        return new LocoProviderFactory(new MockHttpClient(), new NullLogger(), 'en', new ArrayLoader());
    }

    public function testDumperIsPassedToTheProvider()
    {
        $this->expectUserDeprecationMessage('Since symfony/loco-translation-provider 8.2: "Symfony\Component\Translation\Bridge\Loco\LocoProviderFactory" constructor "$defaultLocale" parameter has no effect and will be removed in version 9.0.');

        $dumper = new class extends XliffFileDumper {
            public function formatCatalogue(MessageCatalogue $messages, string $domain, array $options = []): string
            {
                return 'dumped by the injected dumper';
            }
        };

        $client = new MockHttpClient([
            new JsonMockResponse([['code' => 'en']]),
            function (string $method, string $url, array $options = []): MockResponse {
                $this->assertSame('dumped by the injected dumper', $options['body']);

                return new MockResponse();
            },
        ]);

        $factory = new LocoProviderFactory($client, new NullLogger(), 'en', new ArrayLoader(), null, $dumper);

        $translatorBag = new TranslatorBag();
        $translatorBag->addCatalogue(new MessageCatalogue('en', ['messages' => ['a' => 'trans_en_a']]));

        $factory->create(new Dsn('loco://API_KEY@default'))->write($translatorBag);
    }
}
