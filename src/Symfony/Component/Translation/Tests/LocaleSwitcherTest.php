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
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * @requires extension intl
 */
class LocaleSwitcherTest extends TestCase
{
    private string $intlLocale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->intlLocale = \Locale::getDefault();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        \Locale::setDefault($this->intlLocale);
    }

    public function testCanSwitchLocale()
    {
        \Locale::setDefault('en');

        $service = new DummyLocaleAware('en');
        $switcher = new LocaleSwitcher('en', [$service]);

        $this->assertSame('en', \Locale::getDefault());
        $this->assertSame('en', $service->getLocale());
        $this->assertSame('en', $switcher->getLocale());

        $switcher->setLocale('fr');

        $this->assertSame('fr', \Locale::getDefault());
        $this->assertSame('fr', $service->getLocale());
        $this->assertSame('fr', $switcher->getLocale());
    }

    public function testCanSwitchLocaleForCallback()
    {
        \Locale::setDefault('en');

        $service = new DummyLocaleAware('en');
        $switcher = new LocaleSwitcher('en', [$service]);

        $this->assertSame('en', \Locale::getDefault());
        $this->assertSame('en', $service->getLocale());
        $this->assertSame('en', $switcher->getLocale());

        $switcher->runWithLocale('fr', function () use ($switcher, $service) {
            $this->assertSame('fr', \Locale::getDefault());
            $this->assertSame('fr', $service->getLocale());
            $this->assertSame('fr', $switcher->getLocale());
        });

        $this->assertSame('en', \Locale::getDefault());
        $this->assertSame('en', $service->getLocale());
        $this->assertSame('en', $switcher->getLocale());
    }

    public function testWithRequestContext()
    {
        $context = new RequestContext();
        $service = new LocaleSwitcher('en', [], $context);

        $this->assertSame('en', $service->getLocale());

        $service->setLocale('fr');

        $this->assertSame('fr', $service->getLocale());
        $this->assertSame('fr', $context->getParameter('_locale'));

        $service->reset();

        $this->assertSame('en', $service->getLocale());
        $this->assertSame('en', $context->getParameter('_locale'));
    }
}

class DummyLocaleAware implements LocaleAwareInterface
{
    public function __construct(private string $locale)
    {
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
