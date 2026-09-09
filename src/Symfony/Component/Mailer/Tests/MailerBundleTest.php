<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\EventListener\InMemoryPgpPublicKeyRepository;
use Symfony\Component\Mailer\EventListener\InMemorySmimeCertificateRepository;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mailer\MailerBundle;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class MailerBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_mailer_bundle_test';
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->varDir);
    }

    public function testTheMailerSendsThroughTheConfiguredTransport()
    {
        $kernel = new TestMailerKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $container->get('test.mailer')->send(new Email()->from('from@example.org')->to('to@example.org')->subject('Hello')->text('World'));

        $events = $container->get('test.message_logger')->getEvents()->getEvents();
        $sent = array_filter($events, static fn (MessageEvent $event) => !$event->isQueued());
        $this->assertCount(1, $sent);

        $envelope = current($sent)->getEnvelope();
        $this->assertSame('sender@example.org', $envelope->getSender()->getAddress());
        $this->assertSame(['redirected@example.org'], array_map(static fn (Address $address) => $address->getAddress(), $envelope->getRecipients()));
    }

    public function testTheTransportsAreRegisteredFromTheDsn()
    {
        $container = $this->load(['dsn' => 'smtp://example.com']);

        $this->assertTrue($container->hasAlias('mailer'));
        $this->assertTrue($container->hasAlias('mailer.default_transport'));
        $this->assertSame(['main' => 'smtp://example.com'], $container->getDefinition('mailer.transports')->getArgument(0));
    }

    public function testTheTransportsAreRegisteredFromTheTransportsOption()
    {
        $container = $this->load(['transports' => ['transport1' => 'smtp://example1.com', 'transport2' => 'smtp://example2.com']]);

        $this->assertSame([
            'transport1' => 'smtp://example1.com',
            'transport2' => 'smtp://example2.com',
        ], $container->getDefinition('mailer.transports')->getArgument(0));
    }

    public function testTheEnvelopeListenerIsWiredWithTheConfiguredAddresses()
    {
        $container = $this->load(['dsn' => 'smtp://example.com', 'envelope' => [
            'sender' => 'sender@example.org',
            'recipients' => ['redirected@example.org'],
            'allowed_recipients' => ['foobar@example\.org'],
        ]]);

        $listener = $container->getDefinition('mailer.envelope_listener');
        $this->assertSame('sender@example.org', $listener->getArgument(0));
        $this->assertSame(['redirected@example.org'], $listener->getArgument(1));
        $this->assertSame(['foobar@example\.org'], $listener->getArgument(2));
    }

    public function testTheDefaultMessageBusIsUsed()
    {
        $container = $this->load([]);

        $this->assertEquals(new Reference('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE), $container->getDefinition('mailer.mailer')->getArgument(1));
    }

    public function testTheConfiguredMessageBusIsUsed()
    {
        $container = $this->load(['message_bus' => 'app.another_bus']);

        $this->assertEquals(new Reference('app.another_bus'), $container->getDefinition('mailer.mailer')->getArgument(1));
    }

    public function testTheMessageBusCanBeDisabled()
    {
        $container = $this->load(['message_bus' => false]);

        $this->assertNull($container->getDefinition('mailer.mailer')->getArgument(1));
    }

    public function testTheRateLimiterOfATransportIsWired()
    {
        $container = $this->load(['transports' => ['main' => ['dsn' => 'smtp://example.com', 'rate_limiter' => 'foo_limiter']]]);

        $locator = $container->getDefinition('mailer.rate_limiter_locator');
        $this->assertEquals(new Reference('limiter.foo_limiter'), $locator->getArgument(0)['main']);
    }

    public function testTheRateLimiterLocatorIsDroppedWhenNoTransportIsLimited()
    {
        $container = $this->load(['dsn' => 'smtp://example.com']);

        $this->assertFalse($container->hasDefinition('mailer.rate_limiter_locator'));
    }

    public function testTheHeadersAreAddedToTheMessageListener()
    {
        $container = $this->load(['dsn' => 'smtp://example.com', 'headers' => [
            'from' => 'from@example.org',
            'X-Custom' => 'foo',
        ]]);

        $calls = $container->getDefinition('mailer.message_listener')->getArgument(0)->getMethodCalls();
        $this->assertSame([['addHeader', ['from', ['from@example.org']]], ['addHeader', ['X-Custom', 'foo']]], $calls);
    }

    public function testTheMessageListenerIsDroppedWhenNoHeaderIsConfigured()
    {
        $container = $this->load(['dsn' => 'smtp://example.com']);

        $this->assertFalse($container->hasDefinition('mailer.message_listener'));
    }

    public function testTrackingAddsATrackingHeader()
    {
        $container = $this->load(['tracking' => ['opens' => false, 'clicks' => false]]);

        $calls = $container->getDefinition('mailer.message_listener')->getArgument(0)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('add', $calls[0][0]);
        $this->assertSame(TrackingHeader::class, $calls[0][1][0]->getClass());
        $this->assertSame([false, false], $calls[0][1][0]->getArguments());
    }

    public function testTrackingYieldsToAnExplicitTrackingHeader()
    {
        $container = $this->load([
            'tracking' => ['opens' => true],
            'headers' => ['X-Track' => 'opens=true; clicks=default'],
        ]);

        $calls = $container->getDefinition('mailer.message_listener')->getArgument(0)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame([['addHeader', ['X-Track', 'opens=true; clicks=default']]], $calls);
    }

    public function testTheSmimeEncrypterIsWiredWithAnInMemoryRepository()
    {
        $container = $this->load(['smime_encrypter' => [
            'certificates' => ['r1@example.com' => '/path/to/r1.crt'],
            'on_missing_certificate' => 'fail',
            'encrypt_for_sender' => true,
        ]]);

        $repository = $container->getDefinition('mailer.smime_encrypter.repository');
        $this->assertSame(InMemorySmimeCertificateRepository::class, $repository->getClass());
        $this->assertSame(['r1@example.com' => '/path/to/r1.crt'], $repository->getArgument(0));

        $listener = $container->getDefinition('mailer.smime_encrypter.listener');
        $this->assertSame('fail', $listener->getArgument(2));
        $this->assertTrue($listener->getArgument(3));
    }

    public function testTheSmimeEncrypterIsWiredWithTheConfiguredRepository()
    {
        $container = $this->load(['smime_encrypter' => ['repository' => 'my_repository']]);

        $this->assertSame('my_repository', (string) $container->getAlias('mailer.smime_encrypter.repository'));

        $listener = $container->getDefinition('mailer.smime_encrypter.listener');
        $this->assertSame('send_unencrypted', $listener->getArgument(2));
        $this->assertFalse($listener->getArgument(3));
    }

    public function testThePgpSignerAndEncrypterAreWired()
    {
        $container = $this->load([
            'pgp_signer' => [
                'secret_key' => '/path/to/secret.asc',
                'public_key' => '/path/to/public.asc',
                'passphrase' => 'passphrase',
                'digest_algorithm' => 'SHA256',
            ],
            'pgp_encrypter' => [
                'keys' => ['r1@example.com' => '/path/to/r1.asc'],
                'cipher_algorithm' => 'AES192',
                'hide_recipients' => true,
                'on_missing_key' => 'skip',
                'encrypt_for_sender' => true,
            ],
        ]);

        $signer = $container->getDefinition('mailer.pgp_signer');
        $this->assertSame('/path/to/secret.asc', $signer->getArgument(0));
        $this->assertSame('/path/to/public.asc', $signer->getArgument(1));
        $this->assertSame('passphrase', $signer->getArgument(2));
        $this->assertSame(['binary' => 'gpg', 'digest_algorithm' => 'SHA256'], $signer->getArgument(3));

        $repository = $container->getDefinition('mailer.pgp_encrypter.repository');
        $this->assertSame(InMemoryPgpPublicKeyRepository::class, $repository->getClass());
        $this->assertSame(['r1@example.com' => '/path/to/r1.asc'], $repository->getArgument(0));

        $this->assertSame([
            'binary' => 'gpg',
            'cipher_algorithm' => 'AES192',
            'timeout' => 60.0,
            'hide_recipients' => true,
        ], $container->getDefinition('mailer.pgp_encrypter')->getArgument(0));

        $listener = $container->getDefinition('mailer.pgp_encrypter.listener');
        $this->assertSame('skip', $listener->getArgument(2));
        $this->assertTrue($listener->getArgument(3));
    }

    public function testTheSignersAreDroppedWhenDisabled()
    {
        $container = $this->load([]);

        foreach (['dkim_signer', 'smime_signer', 'pgp_signer', 'pgp_encrypter'] as $signer) {
            $this->assertFalse($container->hasDefinition('mailer.'.$signer), $signer);
            $this->assertFalse($container->hasDefinition('mailer.'.$signer.'.listener'), $signer);
        }

        $this->assertFalse($container->hasDefinition('mailer.smime_encrypter.listener'));
    }

    public function testTheWebhookRequestParsersAreRegistered()
    {
        $container = $this->load([]);

        $this->assertTrue($container->hasDefinition('mailer.webhook.request_parser.mailgun'));
    }

    public function testTheDataCollectorIsRegisteredInDebugModeOnly()
    {
        $this->assertFalse($this->load([])->hasDefinition('mailer.data_collector'));
        $this->assertTrue($this->load([], true)->hasDefinition('mailer.data_collector'));
    }

    public function testNothingIsRegisteredWhenDisabled()
    {
        $container = $this->load(['enabled' => false]);

        $this->assertFalse($container->hasDefinition('mailer.mailer'));
        $this->assertFalse($container->hasDefinition('mailer.transports'));
        $this->assertFalse($container->hasDefinition('console.command.mailer_test'));
    }

    private function load(array $config, bool $debug = false): ContainerBuilder
    {
        $container = new ContainerBuilder(new ParameterBag(['kernel.debug' => $debug]));
        new MailerBundle()->getContainerExtension()->load([$config], $container);

        return $container;
    }
}

class TestMailerKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new MailerBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('mailer', [
            'dsn' => 'null://null',
            'envelope' => [
                'sender' => 'sender@example.org',
                'recipients' => ['redirected@example.org'],
            ],
        ]);

        $container->services()
            // the message logger is kept only when a profiler or the test client collects it
            ->set('test.client', \stdClass::class)
            ->alias('test.mailer', 'mailer')->public()
            ->alias('test.message_logger', 'mailer.message_logger_listener')->public()
        ;
    }
}
