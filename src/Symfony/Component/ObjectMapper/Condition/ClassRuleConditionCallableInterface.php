<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Condition;

use Symfony\Component\ObjectMapper\ConditionCallableInterface;

/**
 * Marker interface for condition callables that validate class rules.
 *
 * Conditions implementing this interface are evaluated before fetching
 * the property value, allowing early skip of mapping rules based on
 * source/target class checks.
 *
 * @template T of object
 * @template T2 of object
 *
 * @extends ConditionCallableInterface<T, T2>
 */
interface ClassRuleConditionCallableInterface extends ConditionCallableInterface
{
}
