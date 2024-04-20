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

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * This wrapper serves as a marker interface to indicate badge user loaders that should not be overridden by the
 * default user provider.
 *
 * @internal
 */
final class FallbackUserLoader
{
    public function __construct(private $inner)
    {
    }

    public function __invoke(mixed ...$args): ?UserInterface
    {
        return ($this->inner)(...$args);
    }
}
