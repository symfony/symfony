<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass;

use Symfony\Component\DependencyInjection\Attribute\AsCallable;

class Rules
{
    public static int $instantiations = 0;

    public function __construct()
    {
        ++self::$instantiations;
    }

    #[AsCallable(tags: ['app.rule'])]
    public function isAdult(int $age): bool
    {
        return $age >= 18;
    }

    #[AsCallable(tags: [['app.rule' => ['priority' => 10]]], id: 'app.rule.is_even')]
    public static function isEven(int $number): bool
    {
        return 0 === $number % 2;
    }

    #[AsCallable(tags: [['name' => 'app.rule', 'priority' => -5]])]
    public function isMinor(int $age): bool
    {
        return $age < 18;
    }

    public function notExposed(): bool
    {
        return true;
    }
}
