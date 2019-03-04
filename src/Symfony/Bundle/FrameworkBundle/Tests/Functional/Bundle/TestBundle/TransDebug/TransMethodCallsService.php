<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\TransDebug;

use Symfony\Contracts\Translation\TranslatorInterface;

class TransMethodCallsService
{
    private $translator;

    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    public function hello(): string
    {
        return $this->translator->trans('hello_from_method_calls_service');
    }
}
