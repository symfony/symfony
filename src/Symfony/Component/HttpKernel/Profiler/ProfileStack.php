<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Profiler;

use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
final class ProfileStack
{
    /**
     * @var \SplObjectStorage
     */
    private $profiles;

    public function __construct()
    {
        $this->reset();
    }

    public function has(Request $request): bool
    {
        return isset($this->profiles[$request]);
    }

    public function get(Request $request): Profile
    {
        try {
            return $this->profiles[$request];
        } catch (\UnexpectedValueException $e) {
            throw new \InvalidArgumentException('There is no profile in the stack for the passed request.');
        }
    }

    public function set(Request $request, Profile $profile): void
    {
        $this->profiles[$request] = $profile;
    }

    public function reset(): void
    {
        $this->profiles = new \SplObjectStorage();
    }
}
