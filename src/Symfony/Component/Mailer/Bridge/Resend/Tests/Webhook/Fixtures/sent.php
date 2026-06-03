<?php

use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

$wh = new MailerDeliveryEvent(MailerDeliveryEvent::RECEIVED, '172c41ce-ba6d-4281-8a7a-541faa725748', json_decode(file_get_contents(str_replace('.php', '.json', __FILE__)), true));
$wh->setRecipientEmail('test@example.com');
$wh->setTags([]);
$wh->setMetadata([
    'created_at' => '2024-04-08T09:43:09.438Z',
    'email_id' => '172c41ce-ba6d-4281-8a7a-541faa725748',
    'from' => 'test@resend.com',
    'headers' => [
        [
            'name' => 'Sender',
            'value' => 'test@resend.com',
        ],
    ],
    'subject' => 'Test subject',
    'to' => [
        'test@example.com',
    ],
]);
$wh->setReason('');
$wh->setDate(\DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.uP', '2024-04-08T09:43:09.500000Z'));

return $wh;
