<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\HttpFoundation;

use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A request processor using the {@link Request} class of the HttpFoundation
 * component.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class HttpFoundationRequestHandler implements RequestHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handleRequest(FormInterface $form, $request = null)
    {
        if (!$request instanceof Request) {
            throw new UnexpectedTypeException($request, 'Symfony\Component\HttpFoundation\Request');
        }

        $name = $form->getName();
        $method = $form->getConfig()->getMethod();

        if ($method !== $request->getMethod()) {
            return;
        }

        if ('GET' === $method) {
            if ('' === $name) {
                $data = $request->query->all();
            } else {
                // Don't submit GET requests if the form's name does not exist
                // in the request
                if (!$request->query->has($name)) {
                    return;
                }

                $data = $request->query->get($name);
            }
        } else {
            if ('' === $name) {
                $params = $request->request->all();
                $files = $request->files->all();
            } else {
                $default = $form->getConfig()->getCompound() ? array() : null;
                $params = $request->request->get($name, $default);
                $files = $request->files->get($name, $default);
            }

            if (is_array($params) && is_array($files)) {
                $data = array_replace_recursive($params, $files);
            } else {
                $data = $params ?: $files;
            }
        }

        // Don't auto-submit the form unless at least one field is present.
        if ('' === $name && count(array_intersect_key($data, $form->all())) <= 0) {
            return;
        }

        $form->submit($data, 'PATCH' !== $method);
    }
}
