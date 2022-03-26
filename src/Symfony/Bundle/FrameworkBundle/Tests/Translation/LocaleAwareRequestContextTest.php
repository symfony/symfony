<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Translation;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Translation\LocaleAwareRequestContext;
use Symfony\Component\Routing\RequestContext;

class LocaleAwareRequestContextTest extends TestCase
{
    public function testCanSwitchLocale()
    {
        $context = new RequestContext();
        $service = new LocaleAwareRequestContext($context, 'en');

        $this->assertSame('en', $service->getLocale());
        $this->assertNull($context->getParameter('_locale'));

        $service->setLocale('fr');

        $this->assertSame('fr', $service->getLocale());
        $this->assertSame('fr', $context->getParameter('_locale'));
    }
}
