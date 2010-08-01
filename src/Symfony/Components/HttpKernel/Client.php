<?php

namespace Symfony\Components\HttpKernel;

use Symfony\Components\HttpKernel\HttpKernelInterface;
use Symfony\Components\HttpFoundation\Request;
use Symfony\Components\BrowserKit\Client as BaseClient;
use Symfony\Components\BrowserKit\Request as DomRequest;
use Symfony\Components\BrowserKit\Response as DomResponse;
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
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Client extends BaseClient
{
    protected $kernel;

    /**
     * Constructor.
     *
     * @param HttpKernelInterface $kernel    An HttpKernel instance
     * @param array               $server    The server parameters (equivalent of $_SERVER)
     * @param History             $history   A History instance to store the browser history
     * @param CookieJar           $cookieJar A CookieJar instance to store the cookies
     */
    public function __construct(HttpKernelInterface $kernel, array $server = array(), History $history = null, CookieJar $cookieJar = null)
    {
        $this->kernel = $kernel;

        parent::__construct($server, $history, $cookieJar);

        $this->followRedirects = false;
    }

    /**
     * Makes a request.
     *
     * @param Request  $request A Request instance
     *
     * @return Response A Response instance
     */
    protected function doRequest($request)
    {
        return $this->kernel->handle($request);
    }

    /**
     * Returns the script to execute when the request must be insulated.
     *
     * @param Request $request A Request instance
     */
    protected function getScript($request)
    {
        $kernel = serialize($this->kernel);
        $request = serialize($request);

        $r = new \ReflectionClass('\\Symfony\\Framework\\UniversalClassLoader');
        $requirePath = $r->getFileName();

        $symfonyPath = realpath(__DIR__.'/../../..');

        return <<<EOF
<?php

require_once '$requirePath';

\$loader = new Symfony\Framework\UniversalClassLoader();
\$loader->registerNamespaces(array('Symfony' => '$symfonyPath'));
\$loader->register();

\$kernel = unserialize('$kernel');
echo serialize(\$kernel->handle(unserialize('$request')));
EOF;
    }

    /**
     * Converts the BrowserKit request to a HttpKernel request.
     *
     * @param Request $request A Request instance
     *
     * @return Request A Request instance
     */
    protected function filterRequest(DomRequest $request)
    {
        $uri = $request->getUri();
        if (preg_match('#^https?\://([^/]+)/(.*)$#', $uri, $matches)) {
            $uri = '/'.$matches[2];
        }

        return Request::create($uri, $request->getMethod(), $request->getParameters(), $request->getFiles(), $request->getCookies(), $request->getServer());
    }

    /**
     * Converts the HttpKernel response to a BrowserKit response.
     *
     * @param Response $response A Response instance
     *
     * @return Response A Response instance
     */
    protected function filterResponse($response)
    {
        return new DomResponse($response->getContent(), $response->getStatusCode(), $response->headers->all());
    }
}
