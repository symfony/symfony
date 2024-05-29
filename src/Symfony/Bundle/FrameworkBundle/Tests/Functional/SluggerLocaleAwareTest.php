<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Slugger\SlugConstructArgService;

/**
 * @group functional
 */
class SluggerLocaleAwareTest extends AbstractWebTestCase
{
    /**
     * @requires extension intl
     */
    public function testLocalizedSlugger()
    {
        $kernel = static::createKernel(['test_case' => 'Slugger', 'root_config' => 'config.yml']);
        $kernel->boot();

        $service = $kernel->getContainer()->get(SlugConstructArgService::class);

        $this->assertSame('Stoinostta-tryabva-da-bude-luzha', $service->hello());
    }
}
