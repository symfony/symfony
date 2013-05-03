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
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CsrfValidationListener implements EventSubscriberInterface
{
    /**
     * The name of the CSRF field
     * @var string
     */
    private $fieldName;

    /**
     * The provider for generating and validating CSRF tokens
     * @var CsrfProviderInterface
     */
    private $csrfProvider;

    /**
     * A text mentioning the intention of the CSRF token
     *
     * Validation of the token will only succeed if it was generated in the
     * same session and with the same intention.
     *
     * @var string
     */
    private $intention;

    /**
     * The message displayed in case of an error.
     * @var string
     */
    private $errorMessage;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var null|string
     */
    private $translationDomain;

    public static function getSubscribedEvents()
    {
        return array(
            FormEvents::PRE_SUBMIT => 'preSubmit',
        );
    }

    public function __construct($fieldName, CsrfProviderInterface $csrfProvider, $intention, $errorMessage, TranslatorInterface $translator = null, $translationDomain = null)
    {
        $this->fieldName = $fieldName;
        $this->csrfProvider = $csrfProvider;
        $this->intention = $intention;
        $this->errorMessage = $errorMessage;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
    }

    public function preSubmit(FormEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData();

        if ($form->isRoot() && $form->getConfig()->getOption('compound')) {
            if (!isset($data[$this->fieldName]) || !$this->csrfProvider->isCsrfTokenValid($this->intention, $data[$this->fieldName])) {
                $errorMessage = $this->errorMessage;

                if (null !== $this->translator) {
                    $errorMessage = $this->translator->trans($errorMessage, array(), $this->translationDomain);
                }

                $form->addError(new FormError($errorMessage));
            }

            if (is_array($data)) {
                unset($data[$this->fieldName]);
            }
        }

        $event->setData($data);
    }

    /**
     * Alias of {@link preSubmit()}.
     *
     * @deprecated Deprecated since version 2.3, to be removed in 3.0. Use
     *             {@link preSubmit()} instead.
     */
    public function preBind(FormEvent $event)
    {
        $this->preSubmit($event);
    }
}
