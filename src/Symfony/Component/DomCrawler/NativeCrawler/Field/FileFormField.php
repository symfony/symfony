<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\NativeCrawler\Field;

use Symfony\Component\DomCrawler\Field\FileFormFieldTrait;

/**
 * FileFormField represents a file form field (an HTML file input tag).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
class FileFormField extends FormField
{
    use FileFormFieldTrait;
}
