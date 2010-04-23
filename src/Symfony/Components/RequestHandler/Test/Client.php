<?php

namespace Symfony\Components\RequestHandler\Test;

use Symfony\Components\RequestHandler\RequestHandler;
use Symfony\Components\RequestHandler\Request;
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
 * Client simulates a browser and makes requests to a RequestHandler object.
 *
 * @package    Symfony
 * @subpackage Components_RequestHandler
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Client extends BaseClient
{
  protected $requestHandler;
  protected $test;
  protected $testers;

  /**
   * Constructor.
   *
   * @param Symfony\Components\RequestHandler\RequestHandler $requestHandler A RequestHandler instance
   * @param array                                            $server         The server parameters (equivalent of $_SERVER)
   * @param Symfony\Components\BrowserKit\History            $history        A History instance to store the browser history
   * @param Symfony\Components\BrowserKit\CookieJar          $cookieJar      A CookieJar instance to store the cookies
   */
  public function __construct(RequestHandler $requestHandler, array $server = array(), History $history = null, CookieJar $cookieJar = null)
  {
    $this->requestHandler = $requestHandler;
    $this->testers = array();

    parent::__construct($server, $history, $cookieJar);

    $this->followRedirects = false;
  }

  /**
   * Sets the \PHPUnit_Framework_TestCase instance associated with this client.
   *
   * @param \PHPUnit_Framework_TestCase $test A \PHPUnit_Framework_TestCase instance
   */
  public function setTestCase(\PHPUnit_Framework_TestCase $test)
  {
    $this->test = $test;
  }

  /**
   * Returns true if the tester is defined.
   *
   * @param string $name The tester alias
   *
   * @return Boolean true if the tester is defined, false otherwise
   */
  public function hasTester($name)
  {
    return isset($this->testers[$name]);
  }

  /**
   * Adds an tester object for this client.
   *
   * @param string                                  $name   The tester alias
   * @param Symfony\Foundation\Test\TesterInterface $tester A Tester instance
   */
  public function addTester($name, TesterInterface $tester)
  {
    $this->testers[$name] = $tester;
  }

  /**
   * Gets an tester by name.
   *
   * @param string $name The tester alias
   *
   * @return Symfony\Foundation\Test\TesterInterface An Tester instance
   */
  public function getTester($name)
  {
    if (!isset($this->testers[$name]))
    {
      throw new \InvalidArgumentException(sprintf('Tester "%s" does not exist.', $name));
    }

    return $this->testers[$name];
  }

  /**
   * Makes a request.
   *
   * @param Symfony\Components\RequestHandler\Request  $request A Request instance
   *
   * @param Symfony\Components\RequestHandler\Response $response A Response instance
   */
  protected function doRequest($request)
  {
    return $this->requestHandler->handle($request);
  }

  /**
   * Returns the script to execute when the request must be insulated.
   *
   * @param Symfony\Components\RequestHandler\Request $request A Request instance
   */
  protected function getScript($request)
  {
    $requestHandler = serialize($this->requestHandler);
    $request = serialize($request);

    $r = new \ReflectionClass('\\Symfony\\Foundation\\UniversalClassLoader');
    $requirePath = $r->getFileName();

    $symfonyPath = realpath(__DIR__.'/../../../..');

    return <<<EOF
<?php

require_once '$requirePath';

\$loader = new Symfony\Foundation\UniversalClassLoader();
\$loader->registerNamespaces(array('Symfony' => '$symfonyPath'));
\$loader->register();

\$requestHandler = unserialize('$requestHandler');
echo serialize(\$requestHandler->handle(unserialize('$request')));
EOF;
  }

  /**
   * Converts the BrowserKit request to a RequestHandler request.
   *
   * @param Symfony\Components\BrowserKit\Request $request A Request instance
   *
   * @return Symfony\Components\RequestHandler\Request A Request instance
   */
  protected function filterRequest(DomRequest $request)
  {
    $uri = $request->getUri();
    if (preg_match('#^https?\://([^/]+)/(.*)$#', $uri, $matches))
    {
      $uri = '/'.$matches[2];
    }

    return Request::createFromUri($uri, $request->getMethod(), $request->getParameters(), $request->getFiles(), $request->getCookies(), $request->getServer());
  }

  /**
   * Converts the RequestHandler response to a BrowserKit response.
   *
   * @param Symfony\Components\RequestHandler\Response $response A Response instance
   *
   * @return Symfony\Components\BrowserKit\Response A Response instance
   */
  protected function filterResponse($response)
  {
    return new DomResponse($response->getContent(), $response->getStatusCode(), $response->getHeaders(), $response->getCookies());
  }

  /**
   * Called when a method does not exit.
   *
   * It forwards assert* methods.
   *
   * @param string $method    The method name to execute
   * @param array  $arguments An array of arguments to pass to the method
   */
  public function __call($method, $arguments)
  {
    if ('assert' !== substr($method, 0, 6))
    {
      throw new \BadMethodCallException(sprintf('Method %s::%s is not defined.', get_class($this), $method));
    }

    // standard PHPUnit assert?
    if (method_exists($this->test, $method))
    {
      return call_user_func_array(array($this->test, $method), $arguments);
    }

    if (!preg_match('/^assert([A-Z].+?)([A-Z].+)$/', $method, $matches))
    {
      throw new \BadMethodCallException(sprintf('Method %s::%s is not defined.', get_class($this), $method));
    }

    // registered tester object?
    $name = strtolower($matches[1]);
    if (!$this->hasTester($name))
    {
      throw new \BadMethodCallException(sprintf('Method %s::%s is not defined (assert object "%s" is not defined).', get_class($this), $method, $name));
    }

    $tester = $this->getTester($name);
    $tester->setTestCase($this->test);

    return call_user_func_array(array($tester, 'assert'.$matches[2]), $arguments);
  }
}
