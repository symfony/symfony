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
            $contentType = $request->getContentType();

            $default = $form->getConfig()->getCompound() ? array() : null;
            switch($contentType) {
                case 'json':
                    $data = json_decode($request->getContent(), true);
                    if (!is_array($data)) {
                        $data = array();
                    }
                    $data = $this->camelCaseArrayKeys($data);
                    if ('' == $name) {
                        $params = $data;
                    } else {
                        $params = isset($data[$name]) ? $data[$name] : $default;
                    }
                    $files = array();
                    break;
                default:
                    if ('' === $name) {
                        $params = $request->request->all();
                        $files = $request->files->all();
                    } else {
                        $params = $request->request->get($name, $default);
                        $files = $request->files->get($name, $default);
                    }
                    break;
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

    private function camelCaseArrayKeys(array $array) {
        $return = array();
        foreach($array as $key => $value) {
            $key = preg_replace_callback('/_(\w)/', function($m) {
                return strtoupper($m[1]);
            }, $key);
            $return[$key] = is_array($value) ? $this->camelCaseArrayKeys($value) : $value;
        }
        return $return;
    }
}
