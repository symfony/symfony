<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\FormLoginBundle\Security\User;

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;

class FilesystemUserProvider extends InMemoryUserProvider
{
    public static function getFilename($testCase)
    {
        return sys_get_temp_dir().'/'.Kernel::VERSION.'/'. $testCase .'/user.txt';
    }

    public function __construct($testCase)
    {
        $users = json_decode(file_get_contents(self::getFilename($testCase)), true);

        parent::__construct($users);
    }
}
