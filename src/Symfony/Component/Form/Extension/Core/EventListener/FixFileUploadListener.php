<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\EventListener;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Events;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\TemporaryStorage;

/**
 * Moves uploaded files to a temporary location
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class FixFileUploadListener implements EventSubscriberInterface
{
    private $storage;

    public function __construct(TemporaryStorage $storage)
    {
        $this->storage = $storage;
    }

    public static function getSubscribedEvents()
    {
        return Events::onBindClientData;
    }

    public function onBindClientData(FilterDataEvent $event)
    {
        $form = $event->getForm();

        // TODO should be disableable

        // TESTME
        $data = array_merge(array(
            'file'  => '',
            'token' => '',
            'name'  => '',
        ), $event->getData());

        
        if ($data['file'] instanceof UploadedFile && $data['file']->isValid()) {
            // Newly uploaded file
            $data['token'] = (string)rand(100000, 999999);
            $directory = $this->storage->getTempDir($data['token']);
            $file = $data['file']->move($directory);
            $data['file'] = $file;
            $data['name'] = $file->getBasename();
        } else if ($data['token'] && $data['name']) {
            // Existing uploaded file
            $path = $this->storage->getTempDir($data['token']) . DIRECTORY_SEPARATOR . $data ['name'];

            if (file_exists($path)) {
                $data['file'] = new File($path);
            }
        } else {
            // Clear other fields if we still don't have a file, but keep
            // possible existing files of the field            
            $data = $form->getNormData();
        }

        $event->setData($data);
    }
}