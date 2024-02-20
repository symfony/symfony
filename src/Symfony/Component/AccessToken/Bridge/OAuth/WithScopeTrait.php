<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare (strict_types=1);

namespace Symfony\Component\AccessToken\Bridge\OAuth;

/**
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
trait WithScopeTrait
{
    private readonly ?array $scope;

    /** @return null|array<string> */
    public function getScope(): ?array
    {
        return $this->scope;
    }

    public function getScopeAsString(): ?string
    {
        return $this->scope ? \implode(' ', $this->scope) : null;
    }
}
