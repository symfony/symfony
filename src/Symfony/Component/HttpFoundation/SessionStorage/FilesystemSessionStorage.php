<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\SessionStorage;

use Symfony\Component\HttpFoundation\FlashBagInterface;

/**
 * FilesystemSessionStorage simulates sessions for functional tests.
 *
 * This storage does not start the session (session_start())
 * as it is not "available" when running tests on the command line.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class FilesystemSessionStorage extends AbstractSessionStorage implements SessionSaveHandlerInterface
{
    /**
     * File path.
     * 
     * @var string
     */
    private $path;

    /**
     * Constructor.
     */
    public function __construct(FlashBagInterface $flashBag, $path, array $options = array())
    {
        $this->path = $path;

        parent::__construct($flashBag, $options);
    }

    public function sessionOpen($savePath, $sessionName)
    {
        return true;
    }
    
    public function sessionClose()
    {
        return true;
    }
    
    public function sessionDestroy()
    {
        $file = $this->path.'/'.session_id().'.session';
        if (is_file($file)) {
            unlink($file);
        }
        
        return true;
    }
    
    public function sessionGc($lifetime)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function sessionRead($sessionId)
    {
        $file = $this->path.'/'.session_id().'.session';
        $data = is_file($file) && is_readable($file) ? file_get_contents($file) : '';
        
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function sessionWrite($sessionId, $data)
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }

        file_put_contents($this->path.'/'.session_id().'.session', $this->data);
    }

    /**
     * Regenerates id that represents this storage.
     *
     * @param  Boolean $destroy Destroy session when regenerating?
     *
     * @return Boolean True if session regenerated, false if error
     *
     * @throws \RuntimeException If an error occurs while regenerating this storage
     *
     * @api
     */
    public function regenerate($destroy = false)
    {
        if ($destroy) {
            $this->data = array();
        }

        return true;
    }
}
