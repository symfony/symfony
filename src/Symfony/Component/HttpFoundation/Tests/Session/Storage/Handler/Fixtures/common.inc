<?php

use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;

$parent = __DIR__;
while (!@file_exists($parent.'/vendor/autoload.php')) {
    if (!@file_exists($parent)) {
        // open_basedir restriction in effect
        break;
    }
    if ($parent === dirname($parent)) {
        echo "vendor/autoload.php not found\n";
        exit(1);
    }

    $parent = dirname($parent);
}

require $parent.'/vendor/autoload.php';

error_reporting(-1);
ini_set('html_errors', 0);
ini_set('display_errors', 1);
ini_set('session.gc_probability', 0);
ini_set('session.serialize_handler', 'php');
ini_set('session.cookie_lifetime', 0);
ini_set('session.cookie_domain', '');
ini_set('session.cookie_secure', '');
ini_set('session.cookie_httponly', '');
ini_set('session.use_cookies', 1);
ini_set('session.cache_expire', 180);
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', '');
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.lazy_write', 1);
ini_set('session.name', 'sid');
ini_set('session.save_path', __DIR__);
ini_set('session.cache_limiter', '');

header_remove('X-Powered-By');
header('Content-Type: text/plain; charset=utf-8');

register_shutdown_function(function () {
    echo "\n";
    session_write_close();
    print_r(headers_list());
    echo "shutdown\n";
});
ob_start();

class TestSessionHandler extends AbstractSessionHandler
{
    private $data;
    private ?string $sessionId;

    public function __construct($data = '', $sessionId = null)
    {
        $this->data = $data;
        $this->sessionId = $sessionId;
    }

    public function open(string $path, string $name): bool
    {
        echo __FUNCTION__, "\n";

        return parent::open($path, $name);
    }

    public function validateId(string $sessionId): bool
    {
        echo __FUNCTION__, "\n";

        return parent::validateId($sessionId);
    }

    public function read(string $sessionId): string
    {
        echo __FUNCTION__, "\n";

        return parent::read($sessionId);
    }

    public function updateTimestamp($sessionId, $data): bool
    {
        echo __FUNCTION__, "\n";

        return true;
    }

    public function write(string $sessionId, string $data): bool
    {
        echo __FUNCTION__, "\n";

        return parent::write($sessionId, $data);
    }

    public function destroy(string $sessionId): bool
    {
        echo __FUNCTION__, "\n";

        return parent::destroy($sessionId);
    }

    public function close(): bool
    {
        echo __FUNCTION__, "\n";

        return true;
    }

    public function gc($maxLifetime): int|false
    {
        echo __FUNCTION__, "\n";

        return 1;
    }

    protected function doRead($sessionId): string
    {
        if (isset($this->sessionId) && $sessionId !== $this->sessionId) {
            echo __FUNCTION__ . ": invalid sessionId\n";

            return '';
        }
        echo __FUNCTION__ . ': ', $this->data, "\n";
        $this->sessionId = $sessionId;

        return $this->data;
    }

    protected function doWrite($sessionId, $data): bool
    {
        echo __FUNCTION__.': ', $data, "\n";
        $this->sessionId = $sessionId;

        return true;
    }

    protected function doDestroy($sessionId): bool
    {
        echo __FUNCTION__, "\n";
        $this->sessionId = '';

        return true;
    }
}
