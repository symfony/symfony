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

use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\BrowserKit\Request as DomRequest;
use Symfony\Component\BrowserKit\Response as DomResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Simulates a browser and makes requests to an HttpKernel instance.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @method Request  getRequest()
 * @method Response getResponse()
 */
class HttpKernelBrowser extends AbstractBrowser
{
    protected $kernel;
    private bool $catchExceptions = true;

    /**
     * @param array $server The server parameters (equivalent of $_SERVER)
     */
    public function __construct(HttpKernelInterface $kernel, array $server = [], History $history = null, CookieJar $cookieJar = null)
    {
        // These class properties must be set before calling the parent constructor, as it may depend on it.
        $this->kernel = $kernel;
        $this->followRedirects = false;

        parent::__construct($server, $history, $cookieJar);
    }

    /**
     * Sets whether to catch exceptions when the kernel is handling a request.
     */
    public function catchExceptions(bool $catchExceptions)
    {
        $this->catchExceptions = $catchExceptions;
    }

    /**
     * {@inheritdoc}
     *
     * @param Request $request
     *
     * @return Response
     */
    protected function doRequest(object $request)
    {
        $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, $this->catchExceptions);

        if ($this->kernel instanceof TerminableInterface) {
            $this->kernel->terminate($request, $response);
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     *
     * @param Request $request
     *
     * @return string
     */
    protected function getScript(object $request)
    {
        $kernel = var_export(serialize($this->kernel), true);
        $request = var_export(serialize($request), true);

        $errorReporting = error_reporting();

        $requires = '';
        foreach (get_declared_classes() as $class) {
            if (0 === strpos($class, 'ComposerAutoloaderInit')) {
                $r = new \ReflectionClass($class);
                $file = \dirname($r->getFileName(), 2).'/autoload.php';
                if (file_exists($file)) {
                    $requires .= 'require_once '.var_export($file, true).";\n";
                }
            }
        }

        if (!$requires) {
            throw new \RuntimeException('Composer autoloader not found.');
        }

        $code = <<<EOF
<?php

error_reporting($errorReporting);

$requires

\$kernel = unserialize($kernel);
\$request = unserialize($request);
EOF;

        return $code.$this->getHandleScript();
    }

    protected function getHandleScript()
    {
        return <<<'EOF'
$response = $kernel->handle($request);

if ($kernel instanceof Symfony\Component\HttpKernel\TerminableInterface) {
    $kernel->terminate($request, $response);
}

echo serialize($response);
EOF;
    }

    /**
     * {@inheritdoc}
     */
    protected function filterRequest(DomRequest $request): Request
    {
        $httpRequest = Request::create($request->getUri(), $request->getMethod(), $request->getParameters(), $request->getCookies(), $request->getFiles(), $server = $request->getServer(), $request->getContent());
        if (!isset($server['HTTP_ACCEPT'])) {
            $httpRequest->headers->remove('Accept');
        }

        foreach ($this->filterFiles($httpRequest->files->all()) as $key => $value) {
            $httpRequest->files->set($key, $value);
        }

        return $httpRequest;
    }

    /**
     * Filters an array of files.
     *
     * This method created test instances of UploadedFile so that the move()
     * method can be called on those instances.
     *
     * If the size of a file is greater than the allowed size (from php.ini) then
     * an invalid UploadedFile is returned with an error set to UPLOAD_ERR_INI_SIZE.
     *
     * @see UploadedFile
     */
    protected function filterFiles(array $files): array
    {
        $filtered = [];
        foreach ($files as $key => $value) {
            if (\is_array($value)) {
                $filtered[$key] = $this->filterFiles($value);
            } elseif ($value instanceof UploadedFile) {
                if ($value->isValid() && $value->getSize() > UploadedFile::getMaxFilesize()) {
                    $filtered[$key] = new UploadedFile(
                        '',
                        $value->getClientOriginalName(),
                        $value->getClientMimeType(),
                        \UPLOAD_ERR_INI_SIZE,
                        true
                    );
                } else {
                    $filtered[$key] = new UploadedFile(
                        $value->getPathname(),
                        $value->getClientOriginalName(),
                        $value->getClientMimeType(),
                        $value->getError(),
                        true
                    );
                }
            }
        }

        return $filtered;
    }

    /**
     * {@inheritdoc}
     *
     * @param Response $response
     */
    protected function filterResponse(object $response): DomResponse
    {
        // this is needed to support StreamedResponse
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        return new DomResponse($content, $response->getStatusCode(), $response->headers->all());
    }
}
