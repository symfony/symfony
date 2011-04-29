<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\File;

use Symfony\Component\HttpFoundation\Session;

/**
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class SessionBasedTemporaryStorage extends TemporaryStorage
{
    private $session;

    public function __construct(Session $session, $secret, $directory)
    {
        parent::__construct($secret, $directory);

        $this->session = $session;
    }

    protected function generateHashInfo($token)
    {
        $this->session->start();

        return $this->session->getId().parent::generateHashInfo($token);
    }
}
