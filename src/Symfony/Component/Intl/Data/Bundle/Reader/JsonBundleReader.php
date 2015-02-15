<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Data\Bundle\Reader;

use Symfony\Component\Intl\Exception\ResourceBundleNotFoundException;
use Symfony\Component\Intl\Exception\RuntimeException;

/**
 * Reads .json resource bundles.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class JsonBundleReader implements BundleReaderInterface
{
    /**
     * {@inheritdoc}
     */
    public function read($path, $locale)
    {
        $fileName = $path.'/'.$locale.'.json';

        if (!file_exists($fileName)) {
            throw new ResourceBundleNotFoundException(sprintf(
                'The resource bundle "%s/%s.json" does not exist.',
                $path,
                $locale
            ));
        }

        if (!is_file($fileName)) {
            throw new RuntimeException(sprintf(
                'The resource bundle "%s/%s.json" is not a file.',
                $path,
                $locale
            ));
        }

        $data = json_decode(file_get_contents($fileName), true);

        if (null === $data) {
            throw new RuntimeException(sprintf(
                'The resource bundle "%s/%s.json" contains invalid JSON: %s',
                $path,
                $locale,
<<<<<<< HEAD
                self::getLastJsonError()
=======
                json_last_error_msg()
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
            ));
        }

        return $data;
    }
<<<<<<< HEAD

    /**
     * @return string The last error message created by {@link json_decode()}
     *
     * @link http://de2.php.net/manual/en/function.json-last-error-msg.php#113243
     */
    private static function getLastJsonError()
    {
        if (function_exists('json_last_error_msg')) {
            return json_last_error_msg();
        }

        static $errors = array(
            JSON_ERROR_NONE => null,
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
            JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
            JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
            JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded',
        );

        $error = json_last_error();

        return array_key_exists($error, $errors)
            ? $errors[$error]
            : sprintf('Unknown error (%s)', $error);
    }
=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
}
