<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace Symfony\Component\HttpFoundation\SessionStorage\Persistence;

use Symfony\Component\HttpFoundation\SessionStorage\Persistence\AbstractSessionStoragePersistence;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * NativeSessionStoragePersistence
 *
 * @author Mark de Jong <mail@markdejong.org>
 */
class NativeSessionStoragePersistence extends AbstractSessionStoragePersistence
{
    /**
     * @var string
     */
    private $path;

    /**
     * Constructor.
     *
     * @param EventDispatcherInterface $dispatcher A concrete instance of EventDispatcherInterface
     * @param string $path Path to the directory which will hold the sessions
     *
     */
    public function __construct(EventDispatcherInterface $dispatcher, $path)
    {
        $this->path = $path;

        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }

        parent::__construct($dispatcher);
    }

    private function getFilename($id)
    {
        return sprintf("%s/%s", $this->path, $id);
    }

    /**
     * {@inheritDoc}
     */
    public function open($savePath, $sessionName)
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function close()
    {
        parent::close();
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function write($id, $data)
    {
        $filename = $this->getFilename($id);

        if (false !== ($fp = @fopen($filename, "w"))) {
            $success = fwrite($fp, $data);
            fclose($fp);

            parent::write($id, $data);

            return $success;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function destroy($id)
    {
        parent::destroy($id);

        return @unlink($this->getFilename($id));
    }

    /**
     * {@inheritDoc}
     */
    public function gc($maxlifetime)
    {
        parent::gc($maxlifetime);
        
        foreach (new \DirectoryIterator($this->path) as $entry) {
            if ($entry->isFile()) {
                if ($entry->getMTime() + $maxlifetime < time()) {
                    $this->destroy($entry->getBasename());
                }
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function read($id)
    {
        $filename = $this->getFilename($id);
        $contents = false;

        if (file_exists($filename) && ($fp = @fopen($filename, "r")) !== false) {
            while (!feof($fp)) {
                $contents .= fread($fp, 8192);
            }

            fclose($fp);

            parent::read($id);

            return $contents;
        }

        return $contents;
    }
}
