<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Inflector\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Inflector\Inflector;

class InflectorTest extends TestCase
{
    public function singularizeProvider()
    {
        // see http://english-zone.com/spelling/plurals.html
        // see http://www.scribd.com/doc/3271143/List-of-100-Irregular-Plural-Nouns-in-English
        return array(
            array('accesses', 'access'),
            array('addresses', 'address'),
            array('agendas', 'agenda'),
            array('alumnae', 'alumna'),
            array('alumni', 'alumnus'),
            array('analyses', array('analys', 'analyse', 'analysis')),
            array('antennae', 'antenna'),
            array('antennas', 'antenna'),
            array('appendices', array('appendex', 'appendix', 'appendice')),
            array('arches', array('arch', 'arche')),
            array('atlases', array('atlas', 'atlase', 'atlasis')),
            array('axes', array('ax', 'axe', 'axis')),
            array('babies', 'baby'),
            array('bacteria', array('bacterion', 'bacterium')),
            array('bases', array('bas', 'base', 'basis')),
            array('batches', array('batch', 'batche')),
            array('beaux', 'beau'),
            array('bees', array('be', 'bee')),
            array('boxes', 'box'),
            array('boys', 'boy'),
            array('bureaus', 'bureau'),
            array('bureaux', 'bureau'),
            array('buses', array('bus', 'buse', 'busis')),
            array('bushes', array('bush', 'bushe')),
            array('calves', array('calf', 'calve', 'calff')),
            array('cars', 'car'),
            array('cassettes', array('cassett', 'cassette')),
            array('caves', array('caf', 'cave', 'caff')),
            array('chateaux', 'chateau'),
            array('cheeses', array('chees', 'cheese', 'cheesis')),
            array('children', 'child'),
            array('circuses', array('circus', 'circuse', 'circusis')),
            array('cliffs', 'cliff'),
            array('committee', 'committee'),
            array('crises', array('cris', 'crise', 'crisis')),
            array('criteria', array('criterion', 'criterium')),
            array('cups', 'cup'),
            array('data', array('daton', 'datum')),
            array('days', 'day'),
            array('discos', 'disco'),
            array('devices', array('devex', 'devix', 'device')),
            array('drives', 'drive'),
            array('drivers', 'driver'),
            array('dwarves', array('dwarf', 'dwarve', 'dwarff')),
            array('echoes', array('echo', 'echoe')),
            array('elves', array('elf', 'elve', 'elff')),
            array('emphases', array('emphas', 'emphase', 'emphasis')),
            array('faxes', 'fax'),
            array('feet', 'foot'),
            array('feedback', 'feedback'),
            array('foci', 'focus'),
            array('focuses', array('focus', 'focuse', 'focusis')),
            array('formulae', 'formula'),
            array('formulas', 'formula'),
            array('fungi', 'fungus'),
            array('funguses', array('fungus', 'funguse', 'fungusis')),
            array('garages', array('garag', 'garage')),
            array('geese', 'goose'),
            array('halves', array('half', 'halve', 'halff')),
            array('hats', 'hat'),
            array('heroes', array('hero', 'heroe')),
            array('hippopotamuses', array('hippopotamus', 'hippopotamuse', 'hippopotamusis')), //hippopotami
            array('hoaxes', 'hoax'),
            array('hooves', array('hoof', 'hoove', 'hooff')),
            array('houses', array('hous', 'house', 'housis')),
            array('indexes', 'index'),
            array('indices', array('index', 'indix', 'indice')),
            array('ions', 'ion'),
            array('irises', array('iris', 'irise', 'irisis')),
            array('kisses', 'kiss'),
            array('knives', 'knife'),
            array('lamps', 'lamp'),
            array('leaves', array('leaf', 'leave', 'leaff')),
            array('lice', 'louse'),
            array('lives', 'life'),
            array('matrices', array('matrex', 'matrix', 'matrice')),
            array('matrixes', 'matrix'),
            array('men', 'man'),
            array('mice', 'mouse'),
            array('moves', 'move'),
            array('movies', 'movie'),
            array('nebulae', 'nebula'),
            array('neuroses', array('neuros', 'neurose', 'neurosis')),
            array('news', 'news'),
            array('oases', array('oas', 'oase', 'oasis')),
            array('objectives', 'objective'),
            array('oxen', 'ox'),
            array('parties', 'party'),
            array('people', 'person'),
            array('persons', 'person'),
            array('phenomena', array('phenomenon', 'phenomenum')),
            array('photos', 'photo'),
            array('pianos', 'piano'),
            array('plateaux', 'plateau'),
            array('poppies', 'poppy'),
            array('prices', array('prex', 'prix', 'price')),
            array('quizzes', 'quiz'),
            array('radii', 'radius'),
            array('roofs', 'roof'),
            array('roses', array('ros', 'rose', 'rosis')),
            array('sandwiches', array('sandwich', 'sandwiche')),
            array('scarves', array('scarf', 'scarve', 'scarff')),
            array('schemas', 'schema'), //schemata
            array('selfies', 'selfie'),
            array('series', 'series'),
            array('services', 'service'),
            array('sheriffs', 'sheriff'),
            array('shoes', array('sho', 'shoe')),
            array('spies', 'spy'),
            array('staves', array('staf', 'stave', 'staff')),
            array('stories', 'story'),
            array('strata', array('straton', 'stratum')),
            array('suitcases', array('suitcas', 'suitcase', 'suitcasis')),
            array('syllabi', 'syllabus'),
            array('tags', 'tag'),
            array('teeth', 'tooth'),
            array('theses', array('thes', 'these', 'thesis')),
            array('thieves', array('thief', 'thieve', 'thieff')),
            array('trees', array('tre', 'tree')),
            array('waltzes', array('waltz', 'waltze')),
            array('wives', 'wife'),

            // test casing: if the first letter was uppercase, it should remain so
            array('Men', 'Man'),
            array('GrandChildren', 'GrandChild'),
            array('SubTrees', array('SubTre', 'SubTree')),

            // Known issues
            //array('insignia', 'insigne'),
            //array('insignias', 'insigne'),
            //array('rattles', 'rattle'),
        );
    }

    /**
     * @dataProvider singularizeProvider
     */
    public function testSingularize($plural, $singular)
    {
        $single = Inflector::singularize($plural);
        if (\is_string($singular) && \is_array($single)) {
            $this->fail("--- Expected\n`string`: ".$singular."\n+++ Actual\n`array`: ".implode(', ', $single));
        } elseif (\is_array($singular) && \is_string($single)) {
            $this->fail("--- Expected\n`array`: ".implode(', ', $singular)."\n+++ Actual\n`string`: ".$single);
        }

        $this->assertEquals($singular, $single);
    }
}
