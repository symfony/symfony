<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\EventListener;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Suggests a package, that should be installed (via composer),
 * if the package is missing, and the input command namespace can be mapped to a Symfony bundle.
 *
 * @author Przemysław Bogusz <przemyslaw.bogusz@tubotax.pl>
 *
 * @internal
 */
final class SuggestMissingPackageSubscriber implements EventSubscriberInterface
{
    /**
     * Each namespace maps to the package providing it, limited to packages having a recipe in symfony/recipes
     * or belonging to a family of packages that do, like symfony/ux-*. A "_default" requires a namespace that
     * no Symfony command uses and that a single package owns; otherwise the package only gets the
     * sub-namespaces of the commands it provides.
     */
    private const PACKAGES = [
        'api' => [
            '_default' => ['ApiPlatformBundle', 'api-platform/symfony'],
        ],
        'bazinga' => [
            'js-translation' => ['BazingaJsTranslationBundle', 'willdurand/js-translation-bundle'],
        ],
        'debug' => [
            'live-component' => ['LiveComponentBundle', 'symfony/ux-live-component'],
            'twig-component' => ['TwigComponentBundle', 'symfony/ux-twig-component'],
        ],
        'doctrine' => [
            'fixtures' => ['DoctrineFixturesBundle', 'doctrine/doctrine-fixtures-bundle --dev'],
            'migrations' => ['DoctrineMigrationsBundle', 'doctrine/doctrine-migrations-bundle'],
            'mongodb' => ['DoctrineMongoDBBundle', 'doctrine/mongodb-odm-bundle'],
            '_default' => ['Doctrine ORM', 'symfony/orm-pack'],
        ],
        'hautelook' => [
            'fixtures' => ['HautelookAliceBundle', 'hautelook/alice-bundle --dev'],
        ],
        'league' => [
            'oauth2-server' => ['LeagueOAuth2ServerBundle', 'league/oauth2-server-bundle'],
        ],
        'lexik' => [
            'jwt' => ['LexikJWTAuthenticationBundle', 'lexik/jwt-authentication-bundle'],
        ],
        'make' => [
            'admin' => ['EasyAdminBundle', 'easycorp/easyadmin-bundle'],
            '_default' => ['MakerBundle', 'symfony/maker-bundle --dev'],
        ],
        'sass' => [
            '_default' => ['SassBundle', 'symfonycasts/sass-bundle'],
        ],
        'server' => [
            '_default' => ['Debug Bundle', 'symfony/debug-bundle --dev'],
        ],
        'tailwind' => [
            '_default' => ['TailwindBundle', 'symfonycasts/tailwind-bundle'],
        ],
        'ux' => [
            'icons' => ['UXIconsBundle', 'symfony/ux-icons'],
            'install' => ['UXToolkitBundle', 'symfony/ux-toolkit'],
            'native' => ['UXNativeBundle', 'symfony/ux-native'],
            'toolkit' => ['UXToolkitBundle', 'symfony/ux-toolkit'],
            'translator' => ['UxTranslatorBundle', 'symfony/ux-translator'],
        ],
    ];

    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        if (!$event->getError() instanceof CommandNotFoundException) {
            return;
        }

        [$namespace, $command] = explode(':', $event->getInput()->getFirstArgument()) + [1 => ''];

        if (!isset(self::PACKAGES[$namespace])) {
            return;
        }

        if ($exact = isset(self::PACKAGES[$namespace][$command])) {
            $suggestion = self::PACKAGES[$namespace][$command];
        } elseif (!$suggestion = self::PACKAGES[$namespace]['_default'] ?? null) {
            return;
        }

        $error = $event->getError();

        if ($error->getAlternatives() && !$exact) {
            return;
        }

        $message = \sprintf("%s\n\nYou may be looking for a command provided by the \"%s\" which is currently not installed. Try running \"composer require %s\".", $error->getMessage(), $suggestion[0], $suggestion[1]);
        $event->setError(new CommandNotFoundException($message));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::ERROR => ['onConsoleError', 0],
        ];
    }
}
