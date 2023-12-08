<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Postmark\Tests\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Postmark\Event\PostmarkDeliveryEvent;
use Symfony\Component\Mailer\Bridge\Postmark\Event\PostmarkDeliveryEventFactory;

class PostmarkDeliveryEventFactoryTest extends TestCase
{
    public function testFactorySupportsInactiveRecipient()
    {
        $factory = new PostmarkDeliveryEventFactory();

        $this->assertTrue($factory->supports(PostmarkDeliveryEvent::CODE_INACTIVE_RECIPIENT));
    }
}
