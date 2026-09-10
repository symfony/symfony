<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Routing;

use Symfony\Component\Routing\Matcher\RedirectableCompiledUrlMatcher as BaseRedirectableCompiledUrlMatcher;

trigger_deprecation('symfony/framework-bundle', '8.2', 'The "%s" class is deprecated, use "%s" instead.', RedirectableCompiledUrlMatcher::class, BaseRedirectableCompiledUrlMatcher::class);

/**
 * @deprecated since Symfony 8.2, use Symfony\Component\Routing\Matcher\RedirectableCompiledUrlMatcher instead
 */
class RedirectableCompiledUrlMatcher extends BaseRedirectableCompiledUrlMatcher
{
}
