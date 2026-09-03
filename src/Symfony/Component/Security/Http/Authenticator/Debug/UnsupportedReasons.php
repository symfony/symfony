<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Debug;

/**
 * Collects the reasons why an authenticator did not support a request.
 *
 * When the profiler is enabled, an instance is stored in the
 * SecurityRequestAttributes::UNSUPPORTED_REASONS request attribute for the
 * duration of an authenticator's supports() call, so that the method can
 * explain a negative answer:
 *
 *     $request->attributes->get(SecurityRequestAttributes::UNSUPPORTED_REASONS)?->add('the request is not a POST');
 *
 * The attribute is absent otherwise, so this costs nothing in production.
 */
final class UnsupportedReasons
{
    /**
     * @var string[]
     */
    private array $reasons = [];

    public function add(string $reason): void
    {
        $this->reasons[] = $reason;
    }

    /**
     * @return string[]
     */
    public function all(): array
    {
        return $this->reasons;
    }
}
