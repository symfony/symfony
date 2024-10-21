<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Field;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 *
 * @internal
 */
trait FileFormFieldTrait
{
    /**
     * Sets the PHP error code associated with the field.
     *
     * @param int $error The error code (one of UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE, UPLOAD_ERR_PARTIAL, UPLOAD_ERR_NO_FILE, UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, or UPLOAD_ERR_EXTENSION)
     *
     * @throws \InvalidArgumentException When error code doesn't exist
     */
    public function setErrorCode(int $error): void
    {
        $codes = [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE, \UPLOAD_ERR_PARTIAL, \UPLOAD_ERR_NO_FILE, \UPLOAD_ERR_NO_TMP_DIR, \UPLOAD_ERR_CANT_WRITE, \UPLOAD_ERR_EXTENSION];
        if (!\in_array($error, $codes)) {
            throw new \InvalidArgumentException(\sprintf('The error code "%s" is not valid.', $error));
        }

        $this->value = ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => $error, 'size' => 0];
    }

    /**
     * Sets the value of the field.
     */
    public function upload(?string $value): void
    {
        $this->setValue($value);
    }

    /**
     * Sets the value of the field.
     */
    public function setValue(?string $value): void
    {
        if (null !== $value && is_readable($value)) {
            $error = \UPLOAD_ERR_OK;
            $size = filesize($value);
            $info = pathinfo($value);
            $name = $info['basename'];

            // copy to a tmp location
            $tmp = tempnam(sys_get_temp_dir(), $name);
            if (\array_key_exists('extension', $info)) {
                unlink($tmp);
                $tmp .= '.'.$info['extension'];
            }
            if (is_file($tmp)) {
                unlink($tmp);
            }
            copy($value, $tmp);
            $value = $tmp;
        } else {
            $error = \UPLOAD_ERR_NO_FILE;
            $size = 0;
            $name = '';
            $value = '';
        }

        $this->value = ['name' => $name, 'type' => '', 'tmp_name' => $value, 'error' => $error, 'size' => $size];
    }

    /**
     * Sets path to the file as string for simulating HTTP request.
     */
    public function setFilePath(string $path): void
    {
        parent::setValue($path);
    }

    /**
     * Initializes the form field.
     *
     * @throws \LogicException When node type is incorrect
     */
    protected function initialize(): void
    {
        $nodeName = strtolower($this->node->nodeName);
        if ('input' !== $nodeName) {
            throw new \LogicException(\sprintf('A FileFormField can only be created from an input tag (%s given).', $nodeName));
        }

        $attribute = strtolower($this->node->getAttribute('type') ?? '');
        if ('file' !== $attribute) {
            throw new \LogicException(\sprintf('A FileFormField can only be created from an input tag with a type of file (given type is "%s").', $attribute));
        }

        $this->setValue(null);
    }
}
