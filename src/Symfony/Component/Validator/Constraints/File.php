<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class File extends Constraint
{
    const ERROR_NOT_FOUND = '61fbc820-360e-4635-8410-6e18c55d0793';
    const ERROR_NOT_READABLE = '27fb8e2a-e615-460a-8248-2b9fd7fb0f03';
    const ERROR_MAX_SIZE = '94ab2b21-4bb2-4f2c-8b0f-2f23ee3fa083';
    const ERROR_MIME_TYPE = 'aeaf332e-eb9f-4f5b-954b-73a4ee10099b';

    const ERROR_UPLOAD_INI_SIZE = '3ccb63bf-288f-4a83-9ded-750eb60efeb7';
    const ERROR_UPLOAD_FORM_SIZE = 'd8af3119-4ca1-4b68-b6a8-742fd1713dfb';
    const ERROR_UPLOAD_PARTIAL = '9607840b-25a2-4a06-b6de-a443fdf7bc89';
    const ERROR_UPLOAD_NO_FILE = '5025cb15-812e-411c-8837-cae3106dec7c';
    const ERROR_UPLOAD_NO_TMP_DIR = '6507b3da-97db-4c89-9628-2b41b4772fed';
    const ERROR_UPLOAD_CANT_WRITE = '3da80e51-9c7d-41ea-b077-7385633c9bbc';
    const ERROR_UPLOAD_EXTENSION = 'fc050994-f85a-4226-b35f-7a7e55fe29d4';
    const ERROR_UPLOAD = '4e786105-6f8b-42fa-a409-f29792fa297a';

    public $maxSize = null;
    public $mimeTypes = array();
    public $notFoundMessage = 'The file could not be found.';
    public $notReadableMessage = 'The file is not readable.';
    public $maxSizeMessage = 'The file is too large ({{ size }} {{ suffix }}). Allowed maximum size is {{ limit }} {{ suffix }}.';
    public $mimeTypesMessage = 'The mime type of the file is invalid ({{ type }}). Allowed mime types are {{ types }}.';

    public $uploadIniSizeErrorMessage   = 'The file is too large. Allowed maximum size is {{ limit }} {{ suffix }}.';
    public $uploadFormSizeErrorMessage  = 'The file is too large.';
    public $uploadPartialErrorMessage   = 'The file was only partially uploaded.';
    public $uploadNoFileErrorMessage    = 'No file was uploaded.';
    public $uploadNoTmpDirErrorMessage  = 'No temporary folder was configured in php.ini.';
    public $uploadCantWriteErrorMessage = 'Cannot write temporary file to disk.';
    public $uploadExtensionErrorMessage = 'A PHP extension caused the upload to fail.';
    public $uploadErrorMessage          = 'The file could not be uploaded.';
}
