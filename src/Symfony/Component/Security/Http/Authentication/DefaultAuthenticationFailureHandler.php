<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authentication;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\ParameterBagUtils;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Class with the default authentication failure handling logic.
 *
 * Can be optionally be extended from by the developer to alter the behavior
 * while keeping the default behavior.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Alexander <iam.asm89@gmail.com>
 */
class DefaultAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    protected $httpKernel;
    protected $httpUtils;
    protected $logger;
    protected $options;
    protected $defaultOptions = [
        'failure_path' => null,
        'failure_forward' => false,
        'login_path' => '/login',
        'failure_path_parameter' => '_failure_path',
    ];

    public function __construct(HttpKernelInterface $httpKernel, HttpUtils $httpUtils, array $options = [], ?LoggerInterface $logger = null)
    {
        $this->httpKernel = $httpKernel;
        $this->httpUtils = $httpUtils;
        $this->logger = $logger;
        $this->setOptions($options);
    }

    /**
     * Gets the options.
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return void
     */
    public function setOptions(array $options)
    {
        $this->options = array_merge($this->defaultOptions, $options);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $options = $this->options;
        $failureUrl = ParameterBagUtils::getRequestParameterValue($request, $options['failure_path_parameter']);

        if (\is_string($failureUrl) && (str_starts_with($failureUrl, '/') || str_starts_with($failureUrl, 'http'))) {
            $options['failure_path'] = $failureUrl;
        } elseif ($this->logger && $failureUrl) {
            $this->logger->debug(sprintf('Ignoring query parameter "%s": not a valid URL.', $options['failure_path_parameter']));
        }

        $options['failure_path'] ??= $options['login_path'];

        if ($options['failure_forward']) {
            $this->logger?->debug('Authentication failure, forward triggered.', ['failure_path' => $options['failure_path']]);

            $subRequest = $this->httpUtils->createRequest($request, $options['failure_path']);
            $subRequest->attributes->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

            return $this->httpKernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
        }

        $this->logger?->debug('Authentication failure, redirect triggered.', ['failure_path' => $options['failure_path']]);

        if (!$request->attributes->getBoolean('_stateless')) {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        }

        return $this->httpUtils->createRedirectResponse($request, $options['failure_path']);
    }
}
