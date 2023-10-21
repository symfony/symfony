<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Csrf\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\Util\ServerParams;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CsrfValidationListener implements EventSubscriberInterface
{
    private string $fieldName;
    private CsrfTokenManagerInterface $tokenManager;
    private string $tokenId;
    private string $errorMessage;
    private ?TranslatorInterface $translator;
    private ?string $translationDomain;
    private ServerParams $serverParams;

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SUBMIT => 'preSubmit',
        ];
    }

    public function __construct(string $fieldName, CsrfTokenManagerInterface $tokenManager, string $tokenId, string $errorMessage, TranslatorInterface $translator = null, string $translationDomain = null, ServerParams $serverParams = null)
    {
        $this->fieldName = $fieldName;
        $this->tokenManager = $tokenManager;
        $this->tokenId = $tokenId;
        $this->errorMessage = $errorMessage;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
        $this->serverParams = $serverParams ?? new ServerParams();
    }

    /**
     * @return void
     */
    public function preSubmit(FormEvent $event)
    {
        $form = $event->getForm();
        $postRequestSizeExceeded = 'POST' === $form->getConfig()->getMethod() && $this->serverParams->hasPostMaxSizeBeenExceeded();

        if ($form->isRoot() && $form->getConfig()->getOption('compound') && !$postRequestSizeExceeded) {
            $data = $event->getData();

            $csrfValue = \is_string($data[$this->fieldName] ?? null) ? $data[$this->fieldName] : null;
            $csrfToken = new CsrfToken($this->tokenId, $csrfValue);

            if (null === $csrfValue || !$this->tokenManager->isTokenValid($csrfToken)) {
                $errorMessage = $this->errorMessage;

                if (null !== $this->translator) {
                    $errorMessage = $this->translator->trans($errorMessage, [], $this->translationDomain);
                }

                $form->addError(new FormError($errorMessage, $errorMessage, [], null, $csrfToken));
            }

            if (\is_array($data)) {
                unset($data[$this->fieldName]);
                $event->setData($data);
            }
        }
    }
}
