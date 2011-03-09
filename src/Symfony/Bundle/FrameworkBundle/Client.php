<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Client as BaseClient;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\HttpKernel\Profiler\Profiler as HttpProfiler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Client simulates a browser and makes requests to a Kernel object.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Client extends BaseClient
{
    protected $container;

    /**
     * Constructor.
     *
     * @param HttpKernelInterface $kernel    An HttpKernelInterface instance
     * @param array               $server    The server parameters (equivalent of $_SERVER)
     * @param History             $history   A History instance to store the browser history
     * @param CookieJar           $cookieJar A CookieJar instance to store the cookies
     */
    public function __construct(HttpKernelInterface $kernel, array $server = array(), History $history = null, CookieJar $cookieJar = null)
    {
        parent::__construct($kernel, $server, $history, $cookieJar);

        $this->container = $kernel->getContainer();
    }

    /**
     * Returns the container.
     *
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Returns the kernel.
     *
     * @return HttpKernelInterface
     */
    public function getKernel()
    {
        return $this->kernel;
    }

    /**
     * Gets a profiler for the current Response.
     *
     * @return HttpProfiler A Profiler instance
     */
    public function getProfiler()
    {
        if (!$this->container->has('profiler')) {
            return false;
        }

        return $this->container->get('profiler')->loadFromResponse($this->response);
    }

    /**
     * Makes a request.
     *
     * @param Request $request A Request instance
     *
     * @return Response A Response instance
     */
    protected function doRequest($request)
    {
        $returnValue = $this->kernel->handle($request);
        $this->kernel->shutdown();

        return $returnValue;
    }

    /**
     * Returns the script to execute when the request must be insulated.
     *
     * @param Request $request A Request instance
     *
     * @return string The script content
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
}
