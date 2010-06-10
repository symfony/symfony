<?php

namespace Symfony\Foundation\Test;

use Symfony\Components\HttpKernel\HttpKernelInterface;
use Symfony\Components\HttpKernel\Test\Client as BaseClient;
use Symfony\Components\BrowserKit\History;
use Symfony\Components\BrowserKit\CookieJar;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Client simulates a browser and makes requests to a Kernel object.
 *
 * @package    Symfony
 * @subpackage Foundation
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Client extends BaseClient
{
    protected $kernel;
    protected $container;

    /**
     * Constructor.
     *
     * @param Symfony\Components\HttpKernel\HttpKernelInterface $kernel    A Kernel instance
     * @param array                                             $server    The server parameters (equivalent of $_SERVER)
     * @param Symfony\Components\BrowserKit\History             $history   A History instance to store the browser history
     * @param Symfony\Components\BrowserKit\CookieJar           $cookieJar A CookieJar instance to store the cookies
     */
    public function __construct(HttpKernelInterface $kernel, array $server = array(), History $history = null, CookieJar $cookieJar = null)
    {
        $this->kernel = $kernel;
        $this->container = $kernel->getContainer();

        parent::__construct($kernel, $server, $history, $cookieJar);

        $this->addTestersFromContainer();
    }

    /**
     * Returns the container.
     *
     * @return Symfony\Components\DependencyInjection\Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Returns the kernel.
     *
     * @return Symfony\Foundation\Kernel
     */
    public function getKernel()
    {
        return $this->kernel;
    }

    /**
     * Gets an tester by name.
     *
     * @param string $name The tester alias
     *
     * @return Symfony\Foundation\Test\TesterInterface A Tester instance
     */
    public function getTester($name)
    {
        if (isset($this->testers[$name]) && !is_object($this->testers[$name])) {
            $this->container->setService('test.response', $this->getResponse());

            return $this->container->getService($this->testers[$name]);
        }

        return parent::getTester($name);
    }

    /**
     * Makes a request.
     *
     * @param Symfony\Components\HttpKernel\Request  $request A Request instance
     *
     * @param Symfony\Components\HttpKernel\Response $response A Response instance
     */
    protected function doRequest($request)
    {
        $this->kernel->reboot();

        return $this->kernel->handle($request);
    }

    /**
     * Returns the script to execute when the request must be insulated.
     *
     * @param Symfony\Components\HttpKernel\Request $request A Request instance
     */
    protected function getScript($request)
    {
        $kernel = serialize($this->kernel);
        $request = serialize($request);

        $r = new \ReflectionObject($this->kernel);
        $path = $r->getFileName();

        return <<<EOF
<?php

require_once '$path';

\$kernel = unserialize('$kernel');
\$kernel->boot();
echo serialize(\$kernel->handle(unserialize('$request')));
EOF;
    }

    /**
     * Adds tester objects from the container.
     *
     * This methods adds services with the test.tester annotation as tester objects.
     */
    protected function addTestersFromContainer()
    {
        foreach ($this->container->findAnnotatedServiceIds('test.tester') as $id => $config) {
            if (!isset($config[0]['alias'])) {
                continue;
            }

            $this->testers[$config[0]['alias']] = $id;
        }
    }
}
