<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Mime\DependencyInjection\AddMimeTypeGuesserPass;

/**
 * Provides the MIME types guessing services.
 */
#[RequiredBundle(ServicesBundle::class)]
class MimeBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function boot(): void
    {
        // Instantiate the mime_types service so its setDefault() call fires.
        // The service is made public by AddMimeTypeGuesserPass only when custom guessers are tagged.
        if ($this->container->has('mime_types')) {
            $this->container->get('mime_types');
        }
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AddMimeTypeGuesserPass());
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $configurator->import('Resources/config/mime_type.php');

        $container->registerForAutoconfiguration(MimeTypeGuesserInterface::class)
            ->addTag('mime.mime_type_guesser');
    }
}
