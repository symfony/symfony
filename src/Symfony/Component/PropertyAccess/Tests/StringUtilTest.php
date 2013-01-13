<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests;

use Symfony\Component\PropertyAccess\StringUtil;

class StringUtilTest extends \PHPUnit_Framework_TestCase
{
    public function singularifyProvider()
    {
        // see http://english-zone.com/spelling/plurals.html
        // see http://www.scribd.com/doc/3271143/List-of-100-Irregular-Plural-Nouns-in-English
        return array(
            array('tags', 'tag'),
            array('alumni', 'alumnus'),
            array('funguses', array('fungus', 'funguse', 'fungusis')),
            array('fungi', 'fungus'),
            array('axes', array('ax', 'axe', 'axis')),
            array('appendices', array('appendex', 'appendix', 'appendice')),
            array('indices', array('index', 'indix', 'indice')),
            array('prices', array('prex', 'prix', 'price')),
            array('indexes', 'index'),
            array('children', 'child'),
            array('men', 'man'),
            array('women', 'woman'),
            array('oxen', 'ox'),
            array('bacteria', array('bacterion', 'bacterium')),
            array('criteria', array('criterion', 'criterium')),
            array('feet', 'foot'),
            array('nebulae', 'nebula'),
            array('babies', 'baby'),
            array('hooves', 'hoof'),
            array('chateaux', 'chateau'),
            array('echoes', array('echo', 'echoe')),
            array('analyses', array('analys', 'analyse', 'analysis')),
            array('theses', array('thes', 'these', 'thesis')),
            array('foci', 'focus'),
            array('focuses', array('focus', 'focuse', 'focusis')),
            array('oases', array('oas', 'oase', 'oasis')),
            array('matrices', array('matrex', 'matrix', 'matrice')),
            array('matrixes', 'matrix'),
            array('bureaus', 'bureau'),
            array('bureaux', 'bureau'),
            array('beaux', 'beau'),
            array('data', array('daton', 'datum')),
            array('phenomena', array('phenomenon', 'phenomenum')),
            array('strata', array('straton', 'stratum')),
            array('geese', 'goose'),
            array('teeth', 'tooth'),
            array('antennae', 'antenna'),
            array('antennas', 'antenna'),
            array('houses', array('hous', 'house', 'housis')),
            array('arches', array('arch', 'arche')),
            array('atlases', array('atlas', 'atlase', 'atlasis')),
            array('batches', array('batch', 'batche')),
            array('bushes', array('bush', 'bushe')),
            array('buses', array('bus', 'buse', 'busis')),
            array('calves', 'calf'),
            array('circuses', array('circus', 'circuse', 'circusis')),
            array('crises', array('cris', 'crise', 'crisis')),
            array('dwarves', 'dwarf'),
            array('elves', 'elf'),
            array('emphases', array('emphas', 'emphase', 'emphasis')),
            array('faxes', 'fax'),
            array('halves', 'half'),
            array('heroes', array('hero', 'heroe')),
            array('hoaxes', 'hoax'),
            array('irises', array('iris', 'irise', 'irisis')),
            array('kisses', array('kiss', 'kisse', 'kissis')),
            array('knives', 'knife'),
            array('lives', 'life'),
            array('lice', 'louse'),
            array('mice', 'mouse'),
            array('neuroses', array('neuros', 'neurose', 'neurosis')),
            array('plateaux', 'plateau'),
            array('poppies', 'poppy'),
            array('quizzes', 'quiz'),
            array('scarves', 'scarf'),
            array('spies', 'spy'),
            array('stories', 'story'),
            array('syllabi', 'syllabus'),
            array('thieves', 'thief'),
            array('waltzes', array('waltz', 'waltze')),
            array('wharves', 'wharf'),
            array('wives', 'wife'),
            array('ions', 'ion'),
            array('bases', array('bas', 'base', 'basis')),
            array('cars', 'car'),
            array('cassettes', array('cassett', 'cassette')),
            array('lamps', 'lamp'),
            array('hats', 'hat'),
            array('cups', 'cup'),
            array('boxes', 'box'),
            array('sandwiches', array('sandwich', 'sandwiche')),
            array('suitcases', array('suitcas', 'suitcase', 'suitcasis')),
            array('roses', array('ros', 'rose', 'rosis')),
            array('garages', array('garag', 'garage')),
            array('shoes', array('sho', 'shoe')),
            array('days', 'day'),
            array('boys', 'boy'),
            array('roofs', 'roof'),
            array('cliffs', 'cliff'),
            array('sheriffs', 'sheriff'),
            array('discos', 'disco'),
            array('pianos', 'piano'),
            array('photos', 'photo'),
            array('trees', array('tre', 'tree')),
            array('bees', array('be', 'bee')),
            array('cheeses', array('chees', 'cheese', 'cheesis')),
            array('radii', 'radius'),

            // test casing: if the first letter was uppercase, it should remain so
            array('Men', 'Man'),
            array('GrandChildren', 'GrandChild'),
            array('SubTrees', array('SubTre', 'SubTree')),
        );
    }

    /**
     * @dataProvider singularifyProvider
     */
    public function testSingularify($plural, $singular)
    {
        $this->assertEquals($singular, StringUtil::singularify($plural));
    }
}
