<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Attribute;

trigger_deprecation('symfony/http-kernel', '7.3', \sprintf('The "%s" class is deprecated, use "%s" instead.', AsTargetedValueResolver::class, BaseAsTargetedValueResolver::class));

use Symfony\Component\ArgumentResolver\Attribute\AsTargetedValueResolver as BaseAsTargetedValueResolver;

/**
 * Service tag to autoconfigure targeted value resolvers.
 *
 * deprecated since Symfony 7.3, use {@see BaseAsTargetedValueResolver} instead
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AsTargetedValueResolver extends BaseAsTargetedValueResolver
{
}
