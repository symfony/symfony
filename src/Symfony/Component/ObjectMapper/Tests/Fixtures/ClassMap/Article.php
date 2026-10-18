<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Condition\TargetClass;

final class Article
{
    #[Map(target: 'headline', transform: 'strtoupper')]
    public string $title = 'hello';

    #[Map(target: 'city', transform: [self::class, 'cityName'])]
    public object $address;

    #[Map(if: false)]
    public string $note = 'internal';

    #[Map(target: 'slug')]
    public string $name = 'news';

    #[Map(target: 'excerpt', transform: 'strtoupper', if: new TargetClass(ArticleTeaser::class))]
    public string $body = 'lorem ipsum';

    public function __construct()
    {
        $this->address = (object) ['city' => 'Paris'];
    }

    public static function cityName(object $address): string
    {
        return strtoupper($address->city);
    }
}
