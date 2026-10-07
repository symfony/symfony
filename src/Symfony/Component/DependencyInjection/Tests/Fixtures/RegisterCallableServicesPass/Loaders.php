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

final class Loaders
{
    public static int $instantiations = 0;

    public function __construct()
    {
        ++self::$instantiations;
    }

    #[AsCallable(tags: [['app.loader' => ['key' => 'adult']]])]
    public function loadAdults(int $age): bool
    {
        return $age >= 18;
    }

    #[AsCallable(tags: [
        ['app.loader' => ['key' => 'minor']],
        ['app.loader' => ['key' => 'young']],
    ])]
    public function loadMinors(int $age): bool
    {
        return $age < 18;
    }
}
