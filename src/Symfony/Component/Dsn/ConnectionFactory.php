<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Dsn;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ConnectionFactory implements ConnectionFactoryInterface
{
    /**
     * @var array<string> of classes implementing ConnectionFactoryInterface
     */
    private static $factories = [];

    public static function addFactory(string $factory, $prepend = false): void
    {
        if (!is_a($factory, ConnectionFactoryInterface::class, true)) {
            throw new \LogicException(sprintf('Argument to "%s::addFactory()" must be a class string to a class implementing "%s".', self::class, ConnectionFactoryInterface::class));
        }

        if ($prepend) {
            array_unshift(self::$factories, $factory);
        } else {
            self::$factories[] = $factory;
        }
    }

    public static function create(string $dsn): object
    {
        foreach (self::$factories as $factory) {
            if ($factory::supports($dsn)) {
                return $factory::create($dsn);
            }
        }

        //throw new exception
    }

    public static function supports(string $dsn): bool
    {
        foreach (self::$factories as $factory) {
            if ($factory::supports($dsn)) {
                return true;
            }
        }

        return false;
    }
}
