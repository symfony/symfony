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
    public function testXlfFilesAreValid()
    {
        libxml_use_internal_errors(true);

        $translationFiles = glob(__DIR__.'/../../Resources/translations/*.xlf');
        foreach ($translationFiles as $filePath) {
            libxml_clear_errors();
            simplexml_load_file($filePath);
            $errors = libxml_get_errors();

            if (!empty($errors)) {
                $this->renderErrors($errors);
            }

            $this->assertEmpty($errors, sprintf('The "%s" file is not a valid XML file.', realpath($filePath)));
        }
    }

    private function renderErrors(array $errors)
    {
        $firstError = $errors[0];

        echo sprintf("\nErrors found in '%s' file\n", basename($firstError->file));
        echo sprintf("(path: %s)\n\n", realpath($firstError->file));

        foreach ($errors as $error) {
            echo sprintf("  Line %d, Column %d\n", $error->line, $error->column);
            echo sprintf("  %s\n", $error->message);
        }
    }
}
