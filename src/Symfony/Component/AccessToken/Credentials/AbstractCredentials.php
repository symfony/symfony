<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AccessToken\Credentials;

use Symfony\Component\AccessToken\AccessToken;
use Symfony\Component\AccessToken\CredentialsInterface;

/**
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
abstract class AbstractCredentials implements CredentialsInterface
{
    private ?string $id = null;

    /**
     * Compute unique and predictible identifier.
     */
    protected abstract function computeId(): string;

    public function __construct(
        private ?int $defaultLifetime = null
    ) {
    }

    public function getId(): string
    {
        return $this->id ??= $this->computeId();
    }

    public function getDefaultLifetime(): int
    {
        return $this->defaultLifetime ?? AccessToken::DEFAULT_LIFETIME;
    }
}
