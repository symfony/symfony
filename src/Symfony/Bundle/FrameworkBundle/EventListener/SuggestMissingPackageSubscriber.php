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
    private const PACKAGES = [
        'doctrine' => [
            'fixtures' => ['DoctrineFixturesBundle', 'doctrine/doctrine-fixtures-bundle --dev'],
            'mongodb' => ['DoctrineMongoDBBundle', 'doctrine/mongodb-odm-bundle'],
            '_default' => ['Doctrine ORM', 'symfony/orm-pack'],
        ],
        'generate' => [
            '_default' => ['SensioGeneratorBundle', 'sensio/generator-bundle'],
        ],
        'make' => [
            '_default' => ['MakerBundle', 'symfony/maker-bundle --dev'],
        ],
        'server' => [
            'dump' => ['Debug Bundle', 'symfony/debug-bundle --dev'],
            '_default' => ['WebServerBundle', 'symfony/web-server-bundle --dev'],
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

        if (isset(self::PACKAGES[$namespace][$command])) {
            $suggestion = self::PACKAGES[$namespace][$command];
            $exact = true;
        } else {
            $suggestion = self::PACKAGES[$namespace]['_default'];
            $exact = false;
        }

        $error = $event->getError();

        if ($error->getAlternatives() && !$exact) {
            return;
        }

        $message = sprintf("%s\n\nYou may be looking for a command provided by the \"%s\" which is currently not installed. Try running \"composer require %s\".", $error->getMessage(), $suggestion[0], $suggestion[1]);
        $event->setError(new CommandNotFoundException($message));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::ERROR => ['onConsoleError', 0],
        ];
    }
}
