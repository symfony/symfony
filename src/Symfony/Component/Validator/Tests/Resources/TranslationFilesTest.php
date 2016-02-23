<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Resources;

class TranslationFilesTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideTranslationFiles
     */
    public function testTranslationFilesAreValid($filePath)
    {
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        simplexml_load_file($filePath);

        $this->assertSame(array(), libxml_get_errors());
    }

    public function provideTranslationFiles()
    {
        return array_map(
            function ($filePath) { return (array) $filePath; },
            glob(__DIR__.'/../../Resources/translations/*.xlf')
        );
    }
}
