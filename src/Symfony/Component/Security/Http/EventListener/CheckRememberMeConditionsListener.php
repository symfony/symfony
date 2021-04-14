<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\Event\TokenDeauthenticatedEvent;
use Symfony\Component\Security\Http\ParameterBagUtils;
use Symfony\Component\Security\Http\RememberMe\RememberMeHandlerInterface;

/**
 * Checks if all conditions are met for remember me.
 *
 * The conditions that must be met for this listener to enable remember me:
 *  A) This badge is present in the Passport
 *  B) The remember_me key under your firewall is configured
 *  C) The "remember me" functionality is activated. This is usually
 *      done by having a _remember_me checkbox in your form, but
 *      can be configured by the "always_remember_me" and "remember_me_parameter"
 *      parameters under the "remember_me" firewall key (or "always_remember_me"
 *      is enabled)
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 */
class CheckRememberMeConditionsListener implements EventSubscriberInterface
{
    private $options;
    private $logger;

    public function __construct(array $options = [], ?LoggerInterface $logger = null)
    {
        $this->options = $options + ['always_remember_me' => false, 'remember_me_parameter' => '_remember_me'];
        $this->logger = $logger;
    }

    public function onSuccessfulLogin(LoginSuccessEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(RememberMeBadge::class)) {
            return;
        }

        /** @var RememberMeBadge $badge */
        $badge = $passport->getBadge(RememberMeBadge::class);
        if (!$this->options['always_remember_me']) {
            $parameter = ParameterBagUtils::getRequestParameterValue($event->getRequest(), $this->options['remember_me_parameter']);
            if (!('true' === $parameter || 'on' === $parameter || '1' === $parameter || 'yes' === $parameter || true === $parameter)) {
                if (null !== $this->logger) {
                    $this->logger->debug('Remember me disabled; request does not contain remember me parameter ("{parameter}").', ['parameter' => $this->options['remember_me_parameter']]);
                }

                return;
            }
        }

        $badge->enable();
    }

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => ['onSuccessfulLogin', -32]];
    }
}
