<?php

namespace Symfony\Component\Mailer\Bridge\Sendgrid\Transport;

use Symfony\Component\Mailer\Transport\AbstractTransportSetting;

class SendgridMailSetting extends AbstractTransportSetting
{
    public function __construct(array $value)
    {
        parent::__construct('mail_setting', $value);
    }
}
