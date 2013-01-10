<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Loader;

/**
 * QtFileLoader loads translations from QT Translations XML files.
 *
 * @author Саша Стаменковић <umpirsky@gmail.com>
 *
 * @api
 *
 * @deprecated Deprecated since version 2.2, to be removed in 2.3.
 *             Use QtFileLoader instead.
 */
class QtTranslationsLoader extends QtFileLoader
{
    public function __construct()
    {
        trigger_error('QtTranslationsLoader is deprecated since version 2.2 and will be removed in 2.3. Use QtFileLoader instead.', E_USER_DEPRECATED);
    }
}
