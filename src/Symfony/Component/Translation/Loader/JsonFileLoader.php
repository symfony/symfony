<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Loader;

use Symfony\Component\Translation\Exception\InvalidResourceException;
use Symfony\Component\Translation\Exception\NotFoundResourceException;
use Symfony\Component\Config\Resource\FileResource;

/**
 * JsonFileLoader loads translations from an json file.
 *
 * @author singles
 */
class JsonFileLoader extends ArrayLoader implements LoaderInterface
{
    /**
     * {@inheritdoc}
     */
    public function load($resource, $locale, $domain = 'messages')
    {
        if (!stream_is_local($resource)) {
            throw new InvalidResourceException(sprintf('This is not a local file "%s".', $resource));
        }

        if (!file_exists($resource)) {
            throw new NotFoundResourceException(sprintf('File "%s" not found.', $resource));
        }

        $messages = json_decode(file_get_contents($resource), true);

        if (($errorCode = json_last_error()) > 0) {
            $message = $this->getJSONErrorMessage($errorCode);
            throw new InvalidResourceException(sprintf('Error parsing JSON - %s', $message));
        }

        if ($messages === null) {
            $messages = array();
        }

        $catalogue = parent::load($messages, $locale, $domain);
        $catalogue->addResource(new FileResource($resource));

        return $catalogue;
    }

    /**
     * Translates JSON_ERROR_* constant into meaningful message
     *
     * @param  integer $errorCode Error code returned by json_last_error() call
     * @return string  Message string
     */
    private function getJSONErrorMessage($errorCode)
    {
        $errorMsg = null;
        switch ($errorCode) {
            case JSON_ERROR_DEPTH:
                $errorMsg = 'Maximum stack depth exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $errorMsg = 'Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $errorMsg = 'Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                $errorMsg = 'Syntax error, malformed JSON';
                break;
            case JSON_ERROR_UTF8:
                $errorMsg = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $errorMsg = 'Unknown error';
            break;
        }

        return $errorMsg;
    }
}
