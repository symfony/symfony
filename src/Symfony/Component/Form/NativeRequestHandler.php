<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Util\ServerParams;

/**
 * A request handler using PHP's super globals $_GET, $_POST and $_SERVER.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NativeRequestHandler implements RequestHandlerInterface
{
    /**
     * @var ServerParams
     */
    private $serverParams;

    /**
     * The allowed keys of the $_FILES array.
     *
     * @var array
     */
    private static $fileKeys = array(
        'error',
        'name',
        'size',
        'tmp_name',
        'type',
    );

    public function __construct(ServerParams $params = null)
    {
        $this->serverParams = $params ?: new ServerParams();
    }

    /**
     * {@inheritdoc}
     */
    public function handleRequest(FormInterface $form, $request = null)
    {
        if (null !== $request) {
            throw new UnexpectedTypeException($request, 'null');
        }

        $name = $form->getName();
        $method = $form->getConfig()->getMethod();

        if ($method !== self::getRequestMethod()) {
            return;
        }

        // For request methods that must not have a request body we fetch data
        // from the query string. Otherwise we look for data in the request body.
        if ('GET' === $method || 'HEAD' === $method || 'TRACE' === $method) {
            if ('' === $name) {
                $data = $_GET;
            } else {
                // Don't submit GET requests if the form's name does not exist
                // in the request
                if (!isset($_GET[$name])) {
                    return;
                }

                $data = $_GET[$name];
            }
        } else {
            // Mark the form with an error if the uploaded size was too large
            // This is done here and not in FormValidator because $_POST is
            // empty when that error occurs. Hence the form is never submitted.
            if ($this->serverParams->hasPostMaxSizeBeenExceeded()) {
                // Submit the form, but don't clear the default values
                $form->submit(null, false);

                $form->addError(new FormError(
                    call_user_func($form->getConfig()->getOption('upload_max_size_message')),
                    null,
                    array('{{ max }}' => $this->serverParams->getNormalizedIniPostMaxSize())
                ));

                return;
            }

            $fixedFiles = array();
            foreach ($_FILES as $fileKey => $file) {
                $fixedFiles[$fileKey] = self::stripEmptyFiles(self::fixPhpFilesArray($file));
            }

            if ('' === $name) {
                $params = $_POST;
                $files = $fixedFiles;
            } elseif (array_key_exists($name, $_POST) || array_key_exists($name, $fixedFiles)) {
                $default = $form->getConfig()->getCompound() ? array() : null;
                $params = array_key_exists($name, $_POST) ? $_POST[$name] : $default;
                $files = array_key_exists($name, $fixedFiles) ? $fixedFiles[$name] : $default;
            } else {
                // Don't submit the form if it is not present in the request
                return;
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

    public function isFileUpload($data)
    {
        // POST data will always be strings or arrays of strings. Thus, we can be sure
        // that the submitted data is a file upload if the "error" value is an integer
        // (this value must have been injected by PHP itself).
        return is_array($data) && isset($data['error']) && is_int($data['error']);
    }

    /**
     * Returns the method used to submit the request to the server.
     *
     * @return string The request method
     */
    private static function getRequestMethod()
    {
        $method = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper($_SERVER['REQUEST_METHOD'])
            : 'GET';

        if ('POST' === $method && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        }

        return $method;
    }

    /**
     * Fixes a malformed PHP $_FILES array.
     *
     * PHP has a bug that the format of the $_FILES array differs, depending on
     * whether the uploaded file fields had normal field names or array-like
     * field names ("normal" vs. "parent[child]").
     *
     * This method fixes the array to look like the "normal" $_FILES array.
     *
     * It's safe to pass an already converted array, in which case this method
     * just returns the original array unmodified.
     *
     * This method is identical to {@link \Symfony\Component\HttpFoundation\FileBag::fixPhpFilesArray}
     * and should be kept as such in order to port fixes quickly and easily.
     *
     * @param array $data
     *
     * @return array
     */
    private static function fixPhpFilesArray($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        $keys = array_keys($data);
        sort($keys);

        if (self::$fileKeys !== $keys || !isset($data['name']) || !is_array($data['name'])) {
            return $data;
        }

        $files = $data;
        foreach (self::$fileKeys as $k) {
            unset($files[$k]);
        }

        foreach ($data['name'] as $key => $name) {
            $files[$key] = self::fixPhpFilesArray(array(
                'error' => $data['error'][$key],
                'name' => $name,
                'type' => $data['type'][$key],
                'tmp_name' => $data['tmp_name'][$key],
                'size' => $data['size'][$key],
            ));
        }

        return $files;
    }

    /**
     * Sets empty uploaded files to NULL in the given uploaded files array.
     *
     * @param mixed $data The file upload data
     *
     * @return array|null Returns the stripped upload data
     */
    private static function stripEmptyFiles($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        $keys = array_keys($data);
        sort($keys);

        if (self::$fileKeys === $keys) {
            if (UPLOAD_ERR_NO_FILE === $data['error']) {
                return;
            }

            return $data;
        }

        foreach ($data as $key => $value) {
            $data[$key] = self::stripEmptyFiles($value);
        }

        return $data;
    }
}
