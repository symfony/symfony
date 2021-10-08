<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendgrid\Transport;

use Symfony\Component\Mailer\Transport\AbstractTransportSetting;

class SendgridMailSetting extends AbstractTransportSetting
{
    public function __construct(array $value)
    {
        parent::__construct('mail_setting', $value);
    }
}
