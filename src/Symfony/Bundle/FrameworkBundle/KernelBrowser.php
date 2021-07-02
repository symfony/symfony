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

use Symfony\Bundle\FrameworkBundle\Test\TestBrowserToken;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelBrowser;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profile as HttpProfile;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Simulates a browser and makes requests to a Kernel object.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class KernelBrowser extends HttpKernelBrowser
{
    private $hasPerformedRequest = false;
    private $profiler = false;
    private $reboot = true;

    /**
     * {@inheritdoc}
     */
    public function __construct(KernelInterface $kernel, array $server = [], History $history = null, CookieJar $cookieJar = null)
    {
        parent::__construct($kernel, $server, $history, $cookieJar);
    }

    /**
     * Returns the container.
     *
     * @return ContainerInterface
     */
    public function getContainer()
    {
        $container = $this->kernel->getContainer();

        return $container->has('test.service_container') ? $container->get('test.service_container') : $container;
    }

    /**
     * Returns the kernel.
     *
     * @return KernelInterface
     */
    public function getKernel()
    {
        return $this->kernel;
    }

    /**
     * Gets the profile associated with the current Response.
     *
     * @return HttpProfile|false|null A Profile instance
     */
    public function getProfile()
    {
        if (null === $this->response || !$this->getContainer()->has('profiler')) {
            return false;
        }

        return $this->getContainer()->get('profiler')->loadProfileFromResponse($this->response);
    }

    /**
     * Enables the profiler for the very next request.
     *
     * If the profiler is not enabled, the call to this method does nothing.
     */
    public function enableProfiler()
    {
        if ($this->getContainer()->has('profiler')) {
            $this->profiler = true;
        }
    }

    /**
     * Disables kernel reboot between requests.
     *
     * By default, the Client reboots the Kernel for each request. This method
     * allows to keep the same kernel across requests.
     */
    public function disableReboot()
    {
        $this->reboot = false;
    }

    /**
     * Enables kernel reboot between requests.
     */
    public function enableReboot()
    {
        $this->reboot = true;
    }

    /**
     * @param UserInterface $user
     */
    public function loginUser(object $user, string $firewallContext = 'main'): self
    {
        if (!interface_exists(UserInterface::class)) {
            throw new \LogicException(sprintf('"%s" requires symfony/security-core to be installed.', __METHOD__));
        }

        if (!$user instanceof UserInterface) {
            throw new \LogicException(sprintf('The first argument of "%s" must be instance of "%s", "%s" provided.', __METHOD__, UserInterface::class, \is_object($user) ? \get_class($user) : \gettype($user)));
        }

        $token = new TestBrowserToken($user->getRoles(), $user, $firewallContext);
        $token->setAuthenticated(true);

        $container = $this->getContainer();
        $container->get('security.untracked_token_storage')->setToken($token);

        if (!$container->has('session')) {
            return $this;
        }

        $session = $container->get('session');
        $session->set('_security_'.$firewallContext, serialize($token));
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $this->getCookieJar()->set($cookie);

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @param Request $request
     *
     * @return Response
     */
    protected function doRequest($request)
    {
        // avoid shutting down the Kernel if no request has been performed yet
        // WebTestCase::createClient() boots the Kernel but do not handle a request
        if ($this->hasPerformedRequest && $this->reboot) {
            $this->kernel->shutdown();
        } else {
            $this->hasPerformedRequest = true;
        }

        if ($this->profiler) {
            $this->profiler = false;

            $this->kernel->boot();
            $this->getContainer()->get('profiler')->enable();
        }

        return parent::doRequest($request);
    }

    /**
     * {@inheritdoc}
     *
     * @param Request $request
     *
     * @return Response
     */
    protected function doRequestInProcess($request)
    {
        $response = parent::doRequestInProcess($request);

        $this->profiler = false;

        return $response;
    }

    /**
     * Returns the script to execute when the request must be insulated.
     *
     * It assumes that the autoloader is named 'autoload.php' and that it is
     * stored in the same directory as the kernel (this is the case for the
     * Symfony Standard Edition). If this is not your case, create your own
     * client and override this method.
     *
     * @param Request $request
     *
     * @return string
     */
    protected function getScript($request)
    {
        $kernel = var_export(serialize($this->kernel), true);
        $request = var_export(serialize($request), true);
        $errorReporting = error_reporting();

        $requires = '';
        foreach (get_declared_classes() as $class) {
            if (0 === strpos($class, 'ComposerAutoloaderInit')) {
                $r = new \ReflectionClass($class);
                $file = \dirname($r->getFileName(), 2).'/autoload.php';
                if (is_file($file)) {
                    $requires .= 'require_once '.var_export($file, true).";\n";
                }
            }
        }

        if (!$requires) {
            throw new \RuntimeException('Composer autoloader not found.');
        }

        $requires .= 'require_once '.var_export((new \ReflectionObject($this->kernel))->getFileName(), true).";\n";

        $profilerCode = '';
        if ($this->profiler) {
            $profilerCode = <<<'EOF'
$container = $kernel->getContainer();
$container = $container->has('test.service_container') ? $container->get('test.service_container') : $container;
$container->get('profiler')->enable();
EOF;
        }

        $code = <<<EOF
<?php

error_reporting($errorReporting);

$requires

\$kernel = unserialize($kernel);
\$kernel->boot();
$profilerCode

\$request = unserialize($request);
EOF;

        return $code.$this->getHandleScript();
    }
}
