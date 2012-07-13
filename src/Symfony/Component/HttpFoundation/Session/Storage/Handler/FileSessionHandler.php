<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Storage\Handler;

/**
 * FileSessionHandler.
 *
 * @author Drak <drak@zikula.org>
 */
class FileSessionHandler implements \SessionHandlerInterface
{
    /**
     * @var string
     */
    private $savePath;

    /**
     * @var string
     */
    private $prefix;

    /**
     * @var resource
     */
    private $handle = null;

    /**
     * @var string
     */
    private $currentId = null;

    /**
     * Constructor.
     *
     * @param string $savePath Path of directory to save session files.
     * @param string $prefix
     */
    public function __construct($savePath = null, $prefix = 'sess_')
    {
        if (null === $savePath) {
            $savePath = sys_get_temp_dir();
        }

        $this->savePath = $savePath;
        if (false === is_dir($this->savePath)) {
            mkdir($this->savePath, 0777, true);
        }

        $this->prefix = $prefix;
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionName)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if (null !== $this->handle) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->currentId = null;
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($id)
    {
        $this->initSessionFile($id);
        $data = '';
        fseek($this->handle, 0);
        while (!feof($this->handle)) {
            $data .= fread($this->handle, 1048576);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function write($id, $data)
    {
        $this->initSessionFile($id);
        ftruncate($this->handle, 0);

        return !(false === fwrite($this->handle, $data));
    }

    protected function initSessionFile($id)
    {
        if (null === $this->handle) {
            $this->currentId = $id;
            $this->handle = fopen($this->getPath() . $id, 'a+');
            flock($this->handle, LOCK_EX);
        } elseif ($id != $this->currentId) {
            throw new \RuntimeException('You cannot manage two different sessions at the same time. Close current session before you start new one.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($id)
    {
        $file = $this->getPath().$id;
        if (is_file($file)) {
            unlink($file);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc($maxlifetime)
    {
        foreach (glob($this->getPath().'*') as $file) {
            if ((filemtime($file) + $maxlifetime) < time()) {
                unlink($file);
            }
        }

        return true;
    }

    private function getPath()
    {
        return $this->savePath.'/'.$this->prefix;
    }
}
