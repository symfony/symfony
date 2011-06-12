<?php

namespace Symfony\Component\HttpFoundation\SessionStorage\Persistence;

interface SessionStoragePersistenceInterface
{
	function open($savePath, $sessionName);

	function close();

	function read($id);

	function write($id, $data);

	function destroy($id);

	function gc($maxlifetime);
}
