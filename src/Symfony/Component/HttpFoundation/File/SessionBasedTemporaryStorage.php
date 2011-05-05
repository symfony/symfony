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

    /**
     * Constructor.
     *
     * @param Session  $session     The session
     * @param string   $secret      A secret
     * @param sting    $directory   The base directory
     * @param integer  $size        The maximum size for the temporary storage (in Bytes)
     *                              Should be set to 0 for an unlimited size.
     * @param integer  $ttlSec      The time to live in seconds (a positive number)
     *                              Should be set to 0 for an infinite ttl
     *
     * @throws DirectoryCreationException if the directory does not exist or fails to be created
     */
    public function __construct(Session $session, $secret, $directory, $size = 0, $ttlSec = 0)
    {
        parent::__construct($secret, $directory, $size, $ttlSec);

        $this->session = $session;
    }

    protected function generateHashInfo($token)
    {
        $this->session->start();

        return $this->session->getId().parent::generateHashInfo($token);
    }
}
