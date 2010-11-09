<?php

namespace Symfony\Component\Form;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Field for entering URLs
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
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
