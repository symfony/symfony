<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Dumper;

use Symfony\Component\Translation\MessageCatalogue;

/**
 * Null file dumper used for testing purposes.
 *
 * @author Florian Voutzinos <florian@voutzinos.com>
 */
class NullFileDumper extends FileDumper
{
    /**
     * {@inheritDoc}
     */
    protected function format(MessageCatalogue $messages, $domain)
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    protected function getExtension()
    {
        return 'null';
    }
}
