<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\File;

use Symfony\Component\HttpFoundation\Session;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\Exception\UnexpectedTypeException;

/**
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class SessionBasedTemporaryStorage extends TemporaryStorage
{
    public function __construct(Session $session, $secret, $nestingLevels = 3, $directory = null)
    {
        parent::__construct($directory, $secret, $nestingLevels);

        $this->session = $session;
    }

    protected function generateHashInfo($token)
    {
        $this->session->start();

        return $this->session->getId() . parent::generateHashInfo($token);
    }
}