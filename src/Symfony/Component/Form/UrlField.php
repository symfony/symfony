<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

/**
 * Field for entering URLs.
 *
 * Available options:
 *
 *  * default_protocol:     If specified, {default_protocol}:// (e.g. http://)
 *                          will be prepended onto any input string that
 *                          doesn't begin with the protocol.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class UrlField extends TextField
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addOption('default_protocol', 'http');

        parent::configure();
    }

    /**
     * {@inheritDoc}
     */
    protected function processData($data)
    {
        $protocol = $this->getOption('default_protocol');

        if ($protocol && $data && !preg_match('~^\w+://~', $data)) {
            $data = $protocol . '://' . $data;
        }

        return $data;
    }
}
