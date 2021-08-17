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
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\ParameterBagUtils;

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

    public function __construct(HttpKernelInterface $httpKernel, HttpUtils $httpUtils, array $options = [], LoggerInterface $logger = null)
    {
        $this->httpKernel = $httpKernel;
        $this->httpUtils = $httpUtils;
        $this->logger = $logger;
        $this->setOptions($options);
    }

    /**
     * Gets the options.
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    public function setOptions(array $options)
    {
        $this->options = array_merge($this->defaultOptions, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        if ($failureUrl = ParameterBagUtils::getRequestParameterValue($request, $this->options['failure_path_parameter'])) {
            $this->options['failure_path'] = $failureUrl;
        }

        if (null === $this->options['failure_path']) {
            $this->options['failure_path'] = $this->options['login_path'];
        }

        if ($this->options['failure_forward']) {
            if (null !== $this->logger) {
                $this->logger->debug('Authentication failure, forward triggered.', ['failure_path' => $this->options['failure_path']]);
            }

            $subRequest = $this->httpUtils->createRequest($request, $this->options['failure_path']);
            $subRequest->attributes->set(Security::AUTHENTICATION_ERROR, $exception);

            return $this->httpKernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
        }

        if (null !== $this->logger) {
            $this->logger->debug('Authentication failure, redirect triggered.', ['failure_path' => $this->options['failure_path']]);
        }

        $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);

        return $this->httpUtils->createRedirectResponse($request, $this->options['failure_path']);
    }
}
