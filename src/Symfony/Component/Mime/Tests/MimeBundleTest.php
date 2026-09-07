<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mime\MimeBundle;
use Symfony\Component\Mime\MimeTypeGuesserInterface;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Mime\MimeTypesInterface;

class MimeBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_mime_bundle_test';
        MimeTypes::setDefault(new MimeTypes());
    }

    protected function tearDown(): void
    {
        MimeTypes::setDefault(new MimeTypes());
        (new Filesystem())->remove($this->varDir);
    }

    public function testMimeTypesServiceIsRegistered()
    {
        $kernel = new TestMimeKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $mimeTypes = $container->get('test.mime_types');

        $this->assertInstanceOf(MimeTypes::class, $mimeTypes);
        $this->assertSame($mimeTypes, $container->get('test.mime_types_interface'));
        $this->assertSame($mimeTypes, $container->get('test.mime_type_guesser'));
    }

    public function testCustomGuessersAreRegistered()
    {
        $kernel = new TestMimeKernel('test', true, $this->varDir);
        $kernel->boot();

        $mimeTypes = $kernel->getContainer()->get('test.mime_types');

        $this->assertSame(TaggedMimeTypeGuesser::MIME_TYPE, $mimeTypes->guessMimeType('/probe.tagged'));
        $this->assertSame(AutoconfiguredMimeTypeGuesser::MIME_TYPE, $mimeTypes->guessMimeType('/probe.autoconfigured'));
    }

    public function testBootRegistersTheContainerInstanceAsDefault()
    {
        $kernel = new TestMimeKernel('test', true, $this->varDir);
        $kernel->boot();

        // Fetching the service would fire its setDefault() call on its own, defeating the purpose of this test
        $default = MimeTypes::getDefault();

        $this->assertSame(TaggedMimeTypeGuesser::MIME_TYPE, $default->guessMimeType('/probe.tagged'));
        $this->assertSame($kernel->getContainer()->get('test.mime_types'), $default);
    }
}

class TestMimeKernel extends AbstractKernel
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
        yield new MimeBundle();
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->register(TaggedMimeTypeGuesser::class, TaggedMimeTypeGuesser::class)
            ->addTag('mime.mime_type_guesser');
        $container->register(AutoconfiguredMimeTypeGuesser::class, AutoconfiguredMimeTypeGuesser::class)
            ->setAutoconfigured(true);
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->services()
            ->alias('test.mime_types', 'mime_types')->public()
            ->alias('test.mime_types_interface', MimeTypesInterface::class)->public()
            ->alias('test.mime_type_guesser', MimeTypeGuesserInterface::class)->public()
        ;
    }
}

class TaggedMimeTypeGuesser implements MimeTypeGuesserInterface
{
    public const MIME_TYPE = 'application/x-tagged-guesser';

    public function isGuesserSupported(): bool
    {
        return true;
    }

    public function guessMimeType(string $path): ?string
    {
        return str_ends_with($path, '.tagged') ? self::MIME_TYPE : null;
    }
}

class AutoconfiguredMimeTypeGuesser implements MimeTypeGuesserInterface
{
    public const MIME_TYPE = 'application/x-autoconfigured-guesser';

    public function isGuesserSupported(): bool
    {
        return true;
    }

    public function guessMimeType(string $path): ?string
    {
        return str_ends_with($path, '.autoconfigured') ? self::MIME_TYPE : null;
    }
}
