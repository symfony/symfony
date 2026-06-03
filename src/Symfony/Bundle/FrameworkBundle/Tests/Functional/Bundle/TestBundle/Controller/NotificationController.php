<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

final class NotificationController
{
    public function indexAction(NotifierInterface $notifier)
    {
        $firstNotification = new Notification('Hello World!', ['chat/slack']);
        $firstNotification->content('Symfony is awesome!');
        $notifier->send($firstNotification);

        $secondNotification = (new Notification('New urgent notification'))
            ->importance(Notification::IMPORTANCE_URGENT)
        ;
        $notifier->send($secondNotification);

        $thirdNotification = new Notification('Hello World!', ['sms']);
        $thirdNotification->content('Symfony is awesome!');
        $notifier->send($thirdNotification, new Recipient('', '112'));

        return new Response();
    }
}
