<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

use Symfony\Contracts\Translation\TranslationDomainAwareInterface;

/**
 * @author Jannes Drijkoningen <jannesdrijkoningen@gmail.com>
 */
class TranslationDomainSwitcher implements TranslationDomainAwareInterface
{
    private string $defaultDomain;

    /**
     * @param TranslationDomainAwareInterface[] $domainAwareServices
     */
    public function __construct(
        private string $domain,
        private iterable $domainAwareServices,
    ) {
        $this->defaultDomain = $domain;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
        foreach ($this->domainAwareServices as $service) {
            $service->setDomain($domain);
        }
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Switch to a new domain, execute a callback, then switch back to the original domain.
     *
     * @template T
     *
     * @param callable(string $domain): T $callback
     *
     * @return T
     */
    public function runWithDomain(string $domain, callable $callback): mixed
    {
        $original = $this->getDomain();
        $this->setDomain($domain);

        try {
            return $callback($domain);
        } finally {
            $this->setDomain($original);
        }
    }

    public function reset(): void
    {
        $this->setDomain($this->defaultDomain);
    }
}
