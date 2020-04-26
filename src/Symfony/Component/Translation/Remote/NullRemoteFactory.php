<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Remote;

use Symfony\Component\Translation\Exception\UnsupportedSchemeException;

final class NullRemoteFactory extends AbstractRemoteFactory
{
    /**
     * @return NullRemote
     */
    public function create(Dsn $dsn): RemoteInterface
    {
        if ('null' === $dsn->getScheme()) {
            return new NullRemote($this->dispatcher);
        }

        throw new UnsupportedSchemeException($dsn, 'null', $this->getSupportedSchemes());
    }

    protected function getSupportedSchemes(): array
    {
        return ['null'];
    }
}
