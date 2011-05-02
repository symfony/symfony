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

use Symfony\Component\Form\Events;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Moves uploaded files to a permanent location
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @author Brent Shaffer <bshafs@gmail.com>
 */
class MoveFileUploadListener implements EventSubscriberInterface
{
    protected $path;

    public function __construct($path)
    {
        $this->path = $path;
    }

    public static function getSubscribedEvents()
    {
        return Events::onBindClientData;
    }

    public function onBindClientData(FilterDataEvent $event)
    {
        $data = $event->getData();

        // TODO should be disableable

        // TESTME
        $data = array_merge(array(
            'file' => '',
            'token' => '',
            'name' => '',
        ), $event->getData());

        // Newly uploaded file
        if ($data['file'] instanceof UploadedFile && $data['file']->isValid()) {
            $data['name'] = $data['file']->getName().$data['file']->getExtension();
            $data['file']->move($this->path, $data['name']);
        }

        // Existing uploaded file
        if (!$data['file'] && $data['name']) {
            $path = $this->path . DIRECTORY_SEPARATOR . $data ['name'];

            if (file_exists($path)) {
                $data['file'] = new File($path);
            }
        }

        // Clear other fields if we still don't have a file, but keep
        // possible existing files of the field
        if (!$data['file']) {
            $data = $form->getNormData();
        }

        $event->setData($data);
    }
}