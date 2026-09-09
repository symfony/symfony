<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Factory;

use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Exception\UnsupportedSchemeException;

/**
 * Contract implemented by each KMS bridge to expose the DSN scheme(s) it
 * understands and to build a {@see EncrypterInterface} / {@see DecrypterInterface} from a parsed {@see Dsn}.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
interface KmsFactoryInterface
{
    public function supports(Dsn $dsn): bool;

    /**
     * @throws UnsupportedSchemeException If the DSN scheme is not handled by this factory
     */
    public function create(Dsn $dsn): EncrypterInterface&DecrypterInterface;
}
