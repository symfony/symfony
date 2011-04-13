<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\BrowserKit\Client as BaseClient;
use Symfony\Component\BrowserKit\Request as DomRequest;
use Symfony\Component\BrowserKit\Response as DomResponse;
use Symfony\Component\BrowserKit\Cookie as DomCookie;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\BrowserKit\CookieJar;

/**
 * Client simulates a browser and makes requests to a Kernel object.
 *
 * @author Fabien Potencier <fabien@symfony.com>
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

        $r = new \ReflectionClass('\\Symfony\\Component\\ClassLoader\\UniversalClassLoader');
        $requirePath = $r->getFileName();

        $symfonyPath = realpath(__DIR__.'/../../..');

        return <<<EOF
<?php

require_once '$requirePath';

\$loader = new Symfony\Component\ClassLoader\UniversalClassLoader();
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
        $httpRequest = Request::create($request->getUri(), $request->getMethod(), $request->getParameters(), $request->getCookies(), $request->getFiles(), $request->getServer(), $request->getContent());

        $httpRequest->files->replace($this->filterFiles($httpRequest->files->all()));

        return $httpRequest;
    }

    /**
     * Filters an array of files.
     *
     * This method marks all uploaded files as already moved thus avoiding
     * UploadedFile's call to move_uploaded_file(), which would otherwise fail.
     *
     * @param array $files An array of files
     *
     * @return array An array with all uploaded files marked as already moved
     */
    protected function filterFiles(array $files)
    {
        $filtered = array();
        foreach ($files as $key => $value) {
            if (is_array($value)) {
                $filtered[$key] = $this->filterFiles($value);
            } elseif ($value instanceof UploadedFile) {
                // create an already-moved uploaded file
                $filtered[$key] = new UploadedFile($value->getPath(), $value->getName(), $value->getMimeType(), $value->getSize(), $value->getError(), true);
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
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
        $headers = $response->headers->all();
        if ($response->headers->getCookies()) {
            $cookies = array();
            foreach ($response->headers->getCookies() as $cookie) {
                $cookies[] = new DomCookie($cookie->getName(), $cookie->getValue(), $cookie->getExpiresTime(), $cookie->getPath(), $cookie->getDomain(), $cookie->isSecure(), $cookie->isHttpOnly());
            }
            $headers['Set-Cookie'] = implode(', ', $cookies);
        }

        return new DomResponse($response->getContent(), $response->getStatusCode(), $headers);
    }
}
