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

interface CallableRuleInterface
{
    #[AsCallable(tags: [['app.rule' => [self::class, 'getTagAttributes']]])]
    public function evaluate(int $value): bool;

    public static function getTagAttributes(): array;
}
