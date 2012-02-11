<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Storage;

/**
 * NativeFileSessionStorage.
 *
 * Native session handler using PHP's built in file storage.
 *
 * @author Drak <drak@zikula.org>
 */
class NativeFileSessionStorage extends AbstractSessionStorage
{
    /**
     * @var string
     */
    private $savePath;

    /**
     * Constructor.
     *
     * @param string                $savePath   Path of directory to save session files.
     * @param array                 $options    Session configuration options.
     *
     * @see AbstractSessionStorage::__construct()
     */
    public function __construct($savePath = null, array $options = array())
    {
        if (null === $savePath) {
            $savePath = sys_get_temp_dir();
        }

        if (!is_dir($savePath)) {
            mkdir($savePath, 0777, true);
        }

        $this->savePath = $savePath;

        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     */
    protected function registerSaveHandlers()
    {
        ini_set('session.save_handler', 'files');
        ini_set('session.save_path', $this->savePath);
    }
}
