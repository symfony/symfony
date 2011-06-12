<?php

namespace Symfony\Component\HttpFoundation\SessionStorage\Persistence;

use Symfony\Component\HttpFoundation\SessionStorage\Persistence\AbstractSessionStoragePersistence;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class NativeSessionStoragePersistence extends AbstractSessionStoragePersistence
{
	/**
	 * @var string
	 */
	private $path;

	public function __construct(EventDispatcherInterface $dispatcher)
	{
		$this->path = "D:/www/hosts/rectus/app/tmp/sessions";
		parent::__construct($dispatcher);
	}

	private function getFilename($id)
	{
		return sprintf("%s/%s", $this->path, $id);
	}

	public function open($savePath, $sessionName)
	{
//		$this->path = $savePath;
		return true;
	}

	public function close()
	{
		parent::close();
		return true;
	}

	public function write($id, $data)
	{
		$filename = $this->getFilename($id);

		if(false !== ($fp = @fopen($filename, "w")))
		{
			$success = fwrite($fp, $data);
			fclose($fp);

			parent::write($id, $data);

			return $success;
		}

		return false;
	}

	public function destroy($id)
	{
		parent::destroy($id);

		return @unlink($this->getFilename($id));
	}

	public function gc($maxlifetime)
	{
		foreach(new \DirectoryIterator($this->path) as $entry)
		{
			if($entry->isFile())
			{
				if($entry->getMTime() + $maxlifetime < time())
				{
					$this->destroy($entry->getBasename());
				}
			}
		}

		parent::gc($maxlifetime);

		return true;
	}

	public function read($id)
	{
		$filename = $this->getFilename($id);
		$contents = false;

		if(file_exists($filename) && false !== ($fp = @fread($filename, "r")))
		{
			while (!feof($fp))
			{
				$contents .= fread($fp, 8192);
			}

			fclose($fp);

			parent::read($id);

			return $contents;
		}

		return $contents;
	}
}
