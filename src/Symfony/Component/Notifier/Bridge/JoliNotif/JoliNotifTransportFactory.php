<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\JoliNotif;

use Joli\JoliNotif\DefaultNotifier as JoliNotifier;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;

/**
 * @author Ahmed Ghanem <ahmedghanem7361@gmail.com>
 */
final class JoliNotifTransportFactory extends AbstractTransportFactory
{
    private const SCHEME_NAME = 'jolinotif';

    public function create(Dsn $dsn): JoliNotifTransport
    {
        if (self::SCHEME_NAME !== $dsn->getScheme()) {
            throw new UnsupportedSchemeException($dsn, self::SCHEME_NAME, $this->getSupportedSchemes());
        }

        return (new JoliNotifTransport(new JoliNotifier(), $this->dispatcher))->setHost($dsn->getHost())->setPort($dsn->getPort());
    }

    /**
     * @return string[]
     */
    protected function getSupportedSchemes(): array
    {
        return [
            self::SCHEME_NAME,
        ];
    }
}
