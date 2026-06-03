<?php

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

$parent = __DIR__;
while (!@file_exists($parent.'/vendor/autoload.php')) {
    if (!@file_exists($parent)) {
        // open_basedir restriction in effect
        break;
    }
    if ($parent === dirname($parent)) {
        echo "vendor/autoload.php not found\n";
        exit(1);
    }

    $parent = dirname($parent);
}

require $parent.'/vendor/autoload.php';

error_reporting(-1);
ini_set('html_errors', 0);
ini_set('display_errors', 1);

if (filter_var(ini_get('xdebug.default_enable'), \FILTER_VALIDATE_BOOL)) {
    xdebug_disable();
}

$request = Request::createFromGlobals();

$r = new JsonResponse([
    'request' => $request->request->all(),
    'files' => array_map(
        static fn (UploadedFile $file) => [
            'clientOriginalName' => $file->getClientOriginalName(),
            'clientMimeType' => $file->getClientMimeType(),
            'content' => $file->getContent(),
        ],
        $request->files->all()
    ),
]);

$r->send();
