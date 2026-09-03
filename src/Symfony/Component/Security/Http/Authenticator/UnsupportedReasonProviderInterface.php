<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator;

use Symfony\Component\HttpFoundation\Request;

/**
 * Lets an authenticator tell why it did not support a request.
 *
 * When an authenticator declines a request, nothing says which of its conditions
 * was not met. Implementing this interface makes that reason visible in the
 * profiler, next to the authenticator that was skipped.
 *
 * The reason is only ever asked for when the profiler is enabled, so nothing is
 * computed in production.
 *
 * @author Pascal Cescon <pascal.cescon@gmail.com>
 */
interface UnsupportedReasonProviderInterface
{
    /**
     * Returns why supports() returned false for this request, or null when there is nothing useful to say.
     *
     * This is only called after supports() returned false, and only when the profiler is enabled.
     * It must not have side effects.
     */
    public function getUnsupportedReason(Request $request): ?string;
}
