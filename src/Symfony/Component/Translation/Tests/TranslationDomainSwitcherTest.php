<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslationDomainSwitcher;
use Symfony\Contracts\Translation\TranslationDomainAwareInterface;

class TranslationDomainSwitcherTest extends TestCase
{
    public function testSwitchTranslationDomain()
    {
        $service = new DummyTranslationDomainAware('messages');
        $switcher = new TranslationDomainSwitcher('messages', [$service]);

        $this->assertSame('messages', $service->getDomain());
        $this->assertSame('messages', $switcher->getDomain());

        $switcher->setDomain('app');

        $this->assertSame('app', $service->getDomain());
        $this->assertSame('app', $switcher->getDomain());
    }

    public function testRunWithDomain()
    {
        $service = new DummyTranslationDomainAware('messages');
        $switcher = new TranslationDomainSwitcher('messages', [$service]);

        $this->assertSame('messages', $service->getDomain());
        $this->assertSame('messages', $switcher->getDomain());

        $switcher->runWithDomain('app', function (string $domain) use ($switcher, $service) {
            $this->assertSame('app', $service->getDomain());
            $this->assertSame('app', $switcher->getDomain());
            $this->assertSame('app', $domain);
        });

        $this->assertSame('messages', $service->getDomain());
        $this->assertSame('messages', $switcher->getDomain());
    }
}

class DummyTranslationDomainAware implements TranslationDomainAwareInterface
{
    public function __construct(private string $domain)
    {
    }

    public function setDomain(string $domain)
    {
        $this->domain = $domain;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }
}
