<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Extension\Csrf\EventListener;

use Symphony\Component\EventDispatcher\EventSubscriberInterface;
use Symphony\Component\Form\FormEvents;
use Symphony\Component\Form\FormError;
use Symphony\Component\Form\FormEvent;
use Symphony\Component\Form\Util\ServerParams;
use Symphony\Component\Security\Csrf\CsrfToken;
use Symphony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symphony\Component\Translation\TranslatorInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CsrfValidationListener implements EventSubscriberInterface
{
    private $fieldName;
    private $tokenManager;
    private $tokenId;
    private $errorMessage;
    private $translator;
    private $translationDomain;
    private $serverParams;

    public static function getSubscribedEvents()
    {
        return array(
            FormEvents::PRE_SUBMIT => 'preSubmit',
        );
    }

    public function __construct(string $fieldName, CsrfTokenManagerInterface $tokenManager, string $tokenId, string $errorMessage, TranslatorInterface $translator = null, string $translationDomain = null, ServerParams $serverParams = null)
    {
        $this->fieldName = $fieldName;
        $this->tokenManager = $tokenManager;
        $this->tokenId = $tokenId;
        $this->errorMessage = $errorMessage;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
        $this->serverParams = $serverParams ?: new ServerParams();
    }

    public function preSubmit(FormEvent $event)
    {
        $form = $event->getForm();
        $postRequestSizeExceeded = 'POST' === $form->getConfig()->getMethod() && $this->serverParams->hasPostMaxSizeBeenExceeded();

        if ($form->isRoot() && $form->getConfig()->getOption('compound') && !$postRequestSizeExceeded) {
            $data = $event->getData();

            if (!isset($data[$this->fieldName]) || !$this->tokenManager->isTokenValid(new CsrfToken($this->tokenId, $data[$this->fieldName]))) {
                $errorMessage = $this->errorMessage;

                if (null !== $this->translator) {
                    $errorMessage = $this->translator->trans($errorMessage, array(), $this->translationDomain);
                }

                $form->addError(new FormError($errorMessage));
            }

            if (is_array($data)) {
                unset($data[$this->fieldName]);
                $event->setData($data);
            }
        }
    }
}
