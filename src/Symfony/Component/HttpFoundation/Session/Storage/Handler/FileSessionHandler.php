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
     * Constructor.
     *
     * @param string $savePath Path of directory to save session files.
     */
    public function __construct($savePath)
    {
        $this->savePath = $savePath;
        if (false === is_dir($this->savePath)) {
            mkdir($this->savePath, 0777, true);
        }
    }

    /**
     * {@inheritdoc]
     */
    function open($savePath, $sessionName)
    {
        return true;
    }

    /**
     * {@inheritdoc]
     */
    function close()
    {
        return true;
    }

    /**
     * {@inheritdoc]
     */
    function read($id)
    {
        $file = $this->savePath.'/sess_'.$id;
        if (file_exists($file)) {
            return file_get_contents($file);
        }

        return '';
    }

    /**
     * {@inheritdoc]
     */
    function write($id, $data)
    {
        return false === file_put_contents($this->savePath.'/sess_'.$id, $data) ? false : true;
    }

    /**
     * {@inheritdoc]
     */
    function destroy($id)
    {
        $file = $this->savePath.'/sess_'.$id;
        if (file_exists($file)) {
            unlink($file);
        }

        return true;
    }

    /**
     * {@inheritdoc]
     */
    function gc($maxlifetime)
    {
        foreach (glob($this->savePath.'/sess_*') as $file) {
            if ((filemtime($file) + $maxlifetime) < time()) {
                unlink($file);
            }
        }

        return true;
    }
}
