<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FakeSms;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

/**


 */
final class FakeSmsTransportFactory extends AbstractTransportFactory
{
    protected $mailer;

    public function __construct(MailerInterface $mailer)
    {
        parent::__construct();

        $this->mailer = $mailer;
    }

    /**
     * @return FakeSmsEmailTransport
     */
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if (!\in_array($scheme, $this->getSupportedSchemes())) {
            throw new UnsupportedSchemeException($dsn, 'fakesms', $this->getSupportedSchemes());
        }

        if ('fakesms+email' === $scheme) {
            $mailerTransport = $dsn->getHost();
            $to = $dsn->getRequiredOption('to');
            $from = $dsn->getRequiredOption('from');

            return (new FakeSmsEmailTransport($this->mailer, $to, $from))->setHost($mailerTransport);
        }
    }

    protected function getSupportedSchemes(): array
    {
        return ['fakesms+email'];
    }
}
