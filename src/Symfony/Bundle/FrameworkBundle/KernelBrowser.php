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
use Symfony\Bundle\FrameworkBundle\Test\TestContainer;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\CookieJar;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\BrowserKit\Request as DomRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\HttpKernelBrowser;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profile as HttpProfile;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Simulates a browser and makes requests to a Kernel object.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class KernelBrowser extends HttpKernelBrowser
{
    private bool $hasPerformedRequest = false;
    private bool $profiler = false;
    private bool $reboot = true;
    private ?\Closure $containerConfigurator = null;
    private ?\WeakReference $configuredContainer = null;

    /**
     * @var array<string, TestBrowserToken>
     */
    private array $tokens = [];

    public function __construct(KernelInterface $kernel, array $server = [], ?History $history = null, ?CookieJar $cookieJar = null)
    {
        parent::__construct($kernel, $server, $history, $cookieJar);
    }

    public function getContainer(): ContainerInterface
    {
        $container = $this->kernel->getContainer();

        return $container->has('test.service_container') ? $container->get('test.service_container') : $container;
    }

    public function getKernel(): KernelInterface
    {
        return $this->kernel;
    }

    /**
     * Gets the profile associated with the current Response.
     */
    public function getProfile(): HttpProfile|false|null
    {
        if (!isset($this->response) || !$this->getContainer()->has('profiler')) {
            return false;
        }

        return $this->getContainer()->get('profiler')->loadProfileFromResponse($this->response);
    }

    public function getSession(): ?SessionInterface
    {
        $container = $this->getContainer();

        if (!$container->has('session.factory')) {
            return null;
        }

        $session = $container->get('session.factory')->createSession();

        $cookieJar = $this->getCookieJar();
        $cookie = $cookieJar->get($session->getName());

        if ($cookie instanceof Cookie) {
            $session->setId($cookie->getValue());
        }

        $session->start();

        if (!$cookie instanceof Cookie) {
            $domains = array_unique(array_map(static fn (Cookie $cookie) => $cookie->getName() === $session->getName() ? $cookie->getDomain() : '', $cookieJar->all())) ?: [''];
            foreach ($domains as $domain) {
                $cookieJar->set(new Cookie($session->getName(), $session->getId(), domain: $domain));
            }
        }

        return $session;
    }

    /**
     * Enables the profiler for the very next request.
     *
     * If the profiler is not enabled, the call to this method does nothing.
     */
    public function enableProfiler(): void
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
    public function disableReboot(): void
    {
        $this->reboot = false;
    }

    /**
     * Enables kernel reboot between requests.
     */
    public function enableReboot(): void
    {
        $this->reboot = true;
    }

    /**
     * Registers a callable to configure the container for the lifetime of this client.
     *
     * It is applied right away and then again after each kernel reboot, before the
     * container has been used to handle a request.
     *
     * @internal
     */
    public function setContainerConfigurator(\Closure $configurator): void
    {
        if ($this->insulated) {
            throw new \LogicException('Cannot configure the container when requests are insulated, as closures cannot be passed to the insulated process.');
        }

        $this->containerConfigurator = $configurator;
        $this->configuredContainer = \WeakReference::create($this->kernel->getContainer());
        $configurator($this->getContainer());
    }

    public function insulate(bool $insulated = true): void
    {
        if ($insulated && $this->containerConfigurator) {
            throw new \LogicException('Cannot insulate requests when a container configurator is registered, as closures cannot be passed to the insulated process.');
        }

        parent::insulate($insulated);
    }

    public function restart(): void
    {
        parent::restart();

        $this->tokens = [];
    }

    /**
     * @param UserInterface        $user
     * @param array<string, mixed> $tokenAttributes
     *
     * @return $this
     */
    public function loginUser(object $user, string $firewallContext = 'main', array $tokenAttributes = []): static
    {
        if (!interface_exists(UserInterface::class)) {
            throw new \LogicException(\sprintf('"%s" requires symfony/security-core to be installed. Try running "composer require symfony/security-core".', __METHOD__));
        }

        if (!$user instanceof UserInterface) {
            throw new \LogicException(\sprintf('The first argument of "%s" must be instance of "%s", "%s" provided.', __METHOD__, UserInterface::class, get_debug_type($user)));
        }

        $token = new TestBrowserToken($user->getRoles(), $user, $firewallContext);
        $token->setAttributes($tokenAttributes);

        // stateless firewalls don't read the session, so the token is also sent with each request
        $this->tokens[$firewallContext] = $token;

        $container = $this->getContainer();
        $container->get('security.untracked_token_storage')->setToken($token);

        if (!$session = $this->getSession()) {
            return $this;
        }

        try {
            $session->set('_security_'.$firewallContext, serialize($token));
        } catch (\Throwable $e) {
            $hint = method_exists($user, '__serialize')
                ? 'Review the "__serialize()" implementation to exclude any fields that cannot or should not be persisted there'
                : 'Implement "__serialize()"/"__unserialize()" to control what is serialized into the session, and exclude any fields that should not be persisted there';

            throw new \LogicException(\sprintf('Cannot store the security token in the session: the user object of class "%s" (or one of its referenced objects) is not serializable. %s (e.g. Doctrine relations). See https://symfony.com/doc/current/security.html#understanding-how-users-are-refreshed-from-the-session for details.', $user::class, $hint), 0, $e);
        }
        $session->save();

        return $this;
    }

    protected function filterRequest(DomRequest $request): Request
    {
        $httpRequest = parent::filterRequest($request);

        if ($this->tokens) {
            $httpRequest->attributes->set('_security_test_tokens', $this->tokens);
        }

        return $httpRequest;
    }

    /**
     * Posts a webhook to the application, signed as the "webhook.routing" entry of the given type expects it.
     *
     * Only the routing entries that use the generic request parser of the Webhook component are supported.
     *
     * @param string               $type    The key of the "webhook.routing" entry
     * @param array<string, mixed> $payload The payload, sent as JSON
     * @param string|null          $secret  A secret to sign the webhook with instead of the configured one, to check that it is rejected
     */
    public function sendWebhook(string $type, string $event, array $payload = [], #[\SensitiveParameter] ?string $secret = null): Crawler
    {
        $container = $this->getContainer();

        if (!$container->has('webhook.test.request_factory')) {
            throw new \LogicException(\sprintf('"%s" requires the Webhook component to be enabled. Try running "composer require symfony/webhook".', __METHOD__));
        }

        try {
            $uri = $container->get('router')->generate('_webhook_controller', ['type' => $type]);
        } catch (RouteNotFoundException $e) {
            throw new \LogicException(\sprintf('"%s" requires the "_webhook_controller" route. Did you forget to import "@FrameworkBundle/Resources/config/routing/webhook.php" in your routes?', __METHOD__), 0, $e);
        }

        $request = $container->get('webhook.test.request_factory')->createRequest($uri, $type, $event, $payload, $secret);
        $server = [];

        foreach ($request->headers->all() as $name => $values) {
            $server['content-type' === $name ? 'CONTENT_TYPE' : 'HTTP_'.strtoupper(strtr($name, '-', '_'))] = implode(', ', $values);
        }

        return $this->request('POST', $uri, [], [], $server, $request->getContent());
    }

    /**
     * @param Request $request
     */
    protected function doRequest(object $request): Response
    {
        // avoid shutting down the Kernel if no request has been performed yet
        // WebTestCase::createClient() boots the Kernel but do not handle a request
        if ($this->hasPerformedRequest) {
            // the kernel resets its services before handling the next request, with or without a reboot
            $keptState = $this->keepState();

            if ($this->reboot) {
                $this->kernel->boot();
                $this->kernel->shutdown();

                if ($keptState) {
                    $this->kernel->boot();
                    $container = $this->getContainer();

                    if ($container instanceof TestContainer) {
                        $container->setKeptState($keptState);
                    }
                }
            }
        } else {
            $this->hasPerformedRequest = true;
        }

        if ($this->containerConfigurator) {
            $this->kernel->boot();

            if ($this->configuredContainer?->get() !== $container = $this->kernel->getContainer()) {
                $this->configuredContainer = \WeakReference::create($container);
                ($this->containerConfigurator)($this->getContainer());
            }
        }

        if ($this->profiler) {
            $this->profiler = false;

            $this->kernel->boot();
            $this->getContainer()->get('profiler')->enable();
        }

        return parent::doRequest($request);
    }

    /**
     * @param Request $request
     */
    protected function doRequestInProcess(object $request): Response
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
     */
    protected function getScript(object $request): string
    {
        $kernel = var_export(serialize($this->kernel), true);
        $request = var_export(serialize($request), true);
        $errorReporting = error_reporting();

        $requires = '';
        foreach (get_declared_classes() as $class) {
            if (str_starts_with($class, 'ComposerAutoloaderInit')) {
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

    /**
     * @return array<string, list<object>>
     */
    private function keepState(): array
    {
        try {
            $container = $this->getContainer();
        } catch (\LogicException) {
            // the kernel was shut down, its state is gone already
            return [];
        }

        return $container instanceof TestContainer ? $container->keepState() : [];
    }
}
