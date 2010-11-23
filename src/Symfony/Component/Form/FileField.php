<?php

namespace Symfony\Component\Form;

use Symfony\Component\HttpFoundation\File\File;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * A file field to upload files.
 */
class FileField extends FieldGroup
{
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
     * @see Symfony\Component\Form\FieldGroup::preprocessData()
     */
    protected function preprocessData(array $data)
    {
        if ($data['file']) {
            $data['file']->move($this->getTmpPath($data['token']));
            $data['original_name'] = $data['file']->getOriginalName();
            $data['file'] = '';
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
        srand(microtime(true));

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
     * Returns the absolute temporary file path for the given token
     *
     * @param string $token
     */
    protected function getTmpPath($token)
    {
        return realpath($this->getOption('tmp_dir')) . '/' . $this->getTmpName($token);
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
}
