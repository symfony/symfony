<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\ContactEveryone\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\ContactEveryone\ContactEveryoneOptions;

class ContactEveryoneOptionsTest extends TestCase
{
    public function testContactEveryoneOptions()
    {
        $contactEveryoneOptions = (new ContactEveryoneOptions())->setFrom('test_from')->setCategory('test_category')->setDiffusionName('test_diffusion_name')->setRecipientId('test_recipient');

        self::assertSame([
            'from' => 'test_from',
            'category' => 'test_category',
            'diffusion_name' => 'test_diffusion_name',
        ], $contactEveryoneOptions->toArray());
    }
}
