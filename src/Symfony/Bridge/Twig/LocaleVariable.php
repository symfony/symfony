<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * Exposes helpful methods and parameters related to the locale.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class LocaleVariable implements ServiceSubscriberInterface
{
    public function __construct(
        private ContainerInterface $container,
        public readonly array $enabled,
    ) {
    }

    public static function getSubscribedServices(): array
    {
        return [
            UrlGeneratorInterface::class,
            RequestStack::class,
        ];
    }

    public function __toString(): string
    {
        return $this->getCurrent();
    }

    public function getCurrent(): string
    {
        return \Locale::getDefault();
    }

    /**
     * Generate the current path switched to a new locale (requires i18n routing).
     */
    public function switchCurrentPath(string $locale): string
    {
        // todo error checking
        $request = $this->container->get(RequestStack::class)->getCurrentRequest();

        return $this->container->get(UrlGeneratorInterface::class)->generate(
            $request->attributes->get('_route'),
            \array_merge($request->attributes->get('_route_params'), ['_locale' => $locale])
        );
    }
}
