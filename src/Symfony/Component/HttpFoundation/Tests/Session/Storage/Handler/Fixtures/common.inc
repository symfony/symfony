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
ini_set('session.use_only_cookies', 1);
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

    public function __construct($data = '')
    {
        $this->data = $data;
    }

    public function open($path, $name)
    {
        echo __FUNCTION__, "\n";

        return parent::open($path, $name);
    }

    public function validateId($sessionId)
    {
        echo __FUNCTION__, "\n";

        return parent::validateId($sessionId);
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId)
    {
        echo __FUNCTION__, "\n";

        return parent::read($sessionId);
    }

    /**
     * {@inheritdoc}
     */
    public function updateTimestamp($sessionId, $data)
    {
        echo __FUNCTION__, "\n";

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $data)
    {
        echo __FUNCTION__, "\n";

        return parent::write($sessionId, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId)
    {
        echo __FUNCTION__, "\n";

        return parent::destroy($sessionId);
    }

    public function close()
    {
        echo __FUNCTION__, "\n";

        return true;
    }

    public function gc($maxLifetime)
    {
        echo __FUNCTION__, "\n";

        return true;
    }

    protected function doRead($sessionId)
    {
        echo __FUNCTION__.': ', $this->data, "\n";

        return $this->data;
    }

    protected function doWrite($sessionId, $data)
    {
        echo __FUNCTION__.': ', $data, "\n";

        return true;
    }

    protected function doDestroy($sessionId)
    {
        echo __FUNCTION__, "\n";

        return true;
    }
}
