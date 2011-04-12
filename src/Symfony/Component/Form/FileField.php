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

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * A file field to upload files.
 */
class FileField extends Form
{
    /**
     * Whether the size of the uploaded file exceeds the upload_max_filesize
     * directive in php.ini
     * @var Boolean
     */
    protected $iniSizeExceeded = false;

    /**
     * Whether the size of the uploaded file exceeds the MAX_FILE_SIZE
     * directive specified in the HTML form
     * @var Boolean
     */
    protected $formSizeExceeded = false;

    /**
     * Whether the file was completely uploaded
     * @var Boolean
     */
    protected $uploadComplete = true;

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addRequiredOption('secret');
        $this->addOption('tmp_dir', sys_get_temp_dir());

        parent::configure();

        $this->add(new Field('file'));
        $this->add(new HiddenField('token'));
        $this->add(new HiddenField('original_name'));
    }

    /**
     * Moves the file to a temporary location to prevent its deletion when
     * the PHP process dies
     *
     * This way the file can survive if the form does not validate and is
     * resubmitted.
     *
     * @see Symfony\Component\Form\Form::preprocessData()
     */
    protected function preprocessData(array $data)
    {
        if ($data['file']) {
            if (!$data['file'] instanceof UploadedFile) {
                throw new \UnexpectedValueException('Uploaded file is not of type UploadedFile, your form tag is probably missing the enctype="multipart/form-data" attribute.');
            }
            switch ($data['file']->getError()) {
                case UPLOAD_ERR_INI_SIZE:
                    $this->iniSizeExceeded = true;
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $this->formSizeExceeded = true;
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $this->uploadComplete = false;
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    throw new FormException('Could not upload a file because a temporary directory is missing (UPLOAD_ERR_NO_TMP_DIR)');
                case UPLOAD_ERR_CANT_WRITE:
                    throw new FormException('Could not write file to disk (UPLOAD_ERR_CANT_WRITE)');
                case UPLOAD_ERR_EXTENSION:
                    throw new FormException('A PHP extension stopped the file upload (UPLOAD_ERR_EXTENSION)');
                case UPLOAD_ERR_OK:
                default:
                    $data['original_name'] = $data['file']->getName();
                    $data['file']->move($this->getTmpDir());
                    $data['file']->rename($this->getTmpName($data['token']));
                    $data['file'] = '';
                    break;
            }
        }

        return $data;
    }

    /**
     * Turns a file path into an array of field values
     *
     * @see Symfony\Component\Form\Field::normalize()
     */
    protected function normalize($path)
    {
        return array(
            'file' => '',
            'token' => rand(100000, 999999),
            'original_name' => '',
        );
    }

    /**
     * Turns an array of field values into a file path
     *
     * @see Symfony\Component\Form\Field::denormalize()
     */
    protected function denormalize($data)
    {
        $path = $this->getTmpPath($data['token']);

        return file_exists($path) ? $path : $this->getData();
    }

    /**
     * Returns the absolute temporary path to the uploaded file
     *
     * @param string $token
     */
    protected function getTmpPath($token)
    {
        return $this->getTmpDir() . DIRECTORY_SEPARATOR . $this->getTmpName($token);
    }

    /**
     * Returns the temporary directory where files are stored
     *
     * @param string $token
     */
    protected function getTmpDir()
    {
        return realpath($this->getOption('tmp_dir'));
    }

    /**
     * Returns the temporary file name for the given token
     *
     * @param string $token
     */
    protected function getTmpName($token)
    {
        return md5(session_id() . $this->getOption('secret') . $token);
    }

    /**
     * Returns the original name of the uploaded file
     *
     * @return string
     */
    public function getOriginalName()
    {
        $data = $this->getNormalizedData();

        return $data['original_name'];
    }

    /**
     * {@inheritDoc}
     */
    public function isMultipart()
    {
        return true;
    }

    /**
     * Returns true if the size of the uploaded file exceeds the
     * upload_max_filesize directive in php.ini
     *
     * @return Boolean
     */
    public function isIniSizeExceeded()
    {
        return $this->iniSizeExceeded;
    }

    /**
     * Returns true if the size of the uploaded file exceeds the
     * MAX_FILE_SIZE directive specified in the HTML form
     *
     * @return Boolean
     */
    public function isFormSizeExceeded()
    {
        return $this->formSizeExceeded;
    }

    /**
     * Returns true if the file was completely uploaded
     *
     * @return Boolean
     */
    public function isUploadComplete()
    {
        return $this->uploadComplete;
    }
}
