<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Attribute;

/**
 * Hints the loader that some constraint options are required.
 *
 * @see https://symfony.com/doc/current/validation/custom_constraint.html
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class HasNamedArguments
{
}
