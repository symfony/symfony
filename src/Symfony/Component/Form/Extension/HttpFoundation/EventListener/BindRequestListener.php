<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\HttpFoundation\EventListener;

use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.3, to be removed in 3.0. Pass the
 *             Request instance to {@link Form::process()} instead.
 */
class BindRequestListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        // High priority in order to supersede other listeners
        return array(FormEvents::PRE_BIND => array('preBind', 128));
    }

    public function preBind(FormEvent $event)
    {
        $form = $event->getForm();

        /* @var Request $request */
        $request = $event->getData();

        // Only proceed if we actually deal with a Request
        if (!$request instanceof Request) {
            return;
        }

        // Uncomment this as soon as the deprecation note should be shown
        // trigger_error('Passing a Request instance to Form::submit() is deprecated since version 2.3 and will be disabled in 3.0. Call Form::process($request) instead.', E_USER_DEPRECATED);

        $name = $form->getConfig()->getName();
        $default = $form->getConfig()->getCompound() ? array() : null;

        // Store the bound data in case of a post request
        switch ($request->getMethod()) {
            case 'POST':
            case 'PUT':
            case 'DELETE':
            case 'PATCH':
                if ('' === $name) {
                    // Form bound without name
                    $params = $request->request->all();
                    $files = $request->files->all();
                } else {
                    $params = $request->request->get($name, $default);
                    $files = $request->files->get($name, $default);
                }

                if (is_array($params) && is_array($files)) {
                    $data = array_replace_recursive($params, $files);
                } else {
                    $data = $params ?: $files;
                }

                break;

            case 'GET':
                $data = '' === $name
                    ? $request->query->all()
                    : $request->query->get($name, $default);

                break;

            default:
                throw new LogicException(sprintf(
                    'The request method "%s" is not supported',
                    $request->getMethod()
                ));
        }

        $event->setData($data);
    }
}
