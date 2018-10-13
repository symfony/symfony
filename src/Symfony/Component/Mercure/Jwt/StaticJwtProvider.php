<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Mercure\Jwt;

/**
 * Provides a JWT passed as a configuration parameter.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class StaticJwtProvider
{
    private $jwt;

    public function __construct(string $jwt)
    {
        $this->jwt = $jwt;
    }

    public function __invoke(): string
    {
        return $this->jwt;
    }
}
