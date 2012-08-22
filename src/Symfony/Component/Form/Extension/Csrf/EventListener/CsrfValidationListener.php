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

    public static function getSubscribedEvents()
    {
        return array(
            FormEvents::PRE_BIND => 'preBind',
        );
    }

    public function __construct($fieldName, CsrfProviderInterface $csrfProvider, $intention)
    {
        $this->fieldName = $fieldName;
        $this->csrfProvider = $csrfProvider;
        $this->intention = $intention;
    }

    public function preBind(FormEvent $event)
    {
        $form = $event->getForm();
        $data = $event->getData();

        if ($form->isRoot() && $form->getConfig()->getOption('compound')) {
            if (!isset($data[$this->fieldName]) || !$this->csrfProvider->isCsrfTokenValid($this->intention, $data[$this->fieldName])) {
                $form->addError(new FormError('The CSRF token is invalid. Please try to resubmit the form.'));
            }

            unset($data[$this->fieldName]);
        }

        $event->setData($data);
    }
}
