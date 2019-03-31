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
        return [
            ['accesses', 'access'],
            ['addresses', 'address'],
            ['agendas', 'agenda'],
            ['alumnae', 'alumna'],
            ['alumni', 'alumnus'],
            ['analyses', ['analys', 'analyse', 'analysis']],
            ['antennae', 'antenna'],
            ['antennas', 'antenna'],
            ['appendices', ['appendex', 'appendix', 'appendice']],
            ['arches', ['arch', 'arche']],
            ['atlases', ['atlas', 'atlase', 'atlasis']],
            ['axes', ['ax', 'axe', 'axis']],
            ['babies', 'baby'],
            ['bacteria', ['bacterion', 'bacterium']],
            ['bases', ['bas', 'base', 'basis']],
            ['batches', ['batch', 'batche']],
            ['beaux', 'beau'],
            ['bees', ['be', 'bee']],
            ['boxes', 'box'],
            ['boys', 'boy'],
            ['bureaus', 'bureau'],
            ['bureaux', 'bureau'],
            ['buses', ['bus', 'buse', 'busis']],
            ['bushes', ['bush', 'bushe']],
            ['calves', ['calf', 'calve', 'calff']],
            ['cars', 'car'],
            ['cassettes', ['cassett', 'cassette']],
            ['caves', ['caf', 'cave', 'caff']],
            ['chateaux', 'chateau'],
            ['cheeses', ['chees', 'cheese', 'cheesis']],
            ['children', 'child'],
            ['circuses', ['circus', 'circuse', 'circusis']],
            ['cliffs', 'cliff'],
            ['committee', 'committee'],
            ['crises', ['cris', 'crise', 'crisis']],
            ['criteria', ['criterion', 'criterium']],
            ['cups', 'cup'],
            ['data', ['daton', 'datum']],
            ['days', 'day'],
            ['discos', 'disco'],
            ['devices', ['devex', 'devix', 'device']],
            ['drives', 'drive'],
            ['drivers', 'driver'],
            ['dwarves', ['dwarf', 'dwarve', 'dwarff']],
            ['echoes', ['echo', 'echoe']],
            ['elves', ['elf', 'elve', 'elff']],
            ['emphases', ['emphas', 'emphase', 'emphasis']],
            ['faxes', 'fax'],
            ['feet', 'foot'],
            ['feedback', 'feedback'],
            ['foci', 'focus'],
            ['focuses', ['focus', 'focuse', 'focusis']],
            ['formulae', 'formula'],
            ['formulas', 'formula'],
            ['fungi', 'fungus'],
            ['funguses', ['fungus', 'funguse', 'fungusis']],
            ['garages', ['garag', 'garage']],
            ['geese', 'goose'],
            ['halves', ['half', 'halve', 'halff']],
            ['hats', 'hat'],
            ['heroes', ['hero', 'heroe']],
            ['hippopotamuses', ['hippopotamus', 'hippopotamuse', 'hippopotamusis']], //hippopotami
            ['hoaxes', 'hoax'],
            ['hooves', ['hoof', 'hoove', 'hooff']],
            ['houses', ['hous', 'house', 'housis']],
            ['indexes', 'index'],
            ['indices', ['index', 'indix', 'indice']],
            ['ions', 'ion'],
            ['irises', ['iris', 'irise', 'irisis']],
            ['kisses', 'kiss'],
            ['knives', 'knife'],
            ['lamps', 'lamp'],
            ['leaves', ['leaf', 'leave', 'leaff']],
            ['lice', 'louse'],
            ['lives', 'life'],
            ['matrices', ['matrex', 'matrix', 'matrice']],
            ['matrixes', 'matrix'],
            ['men', 'man'],
            ['mice', 'mouse'],
            ['moves', 'move'],
            ['movies', 'movie'],
            ['nebulae', 'nebula'],
            ['neuroses', ['neuros', 'neurose', 'neurosis']],
            ['news', 'news'],
            ['oases', ['oas', 'oase', 'oasis']],
            ['objectives', 'objective'],
            ['oxen', 'ox'],
            ['parties', 'party'],
            ['people', 'person'],
            ['persons', 'person'],
            ['phenomena', ['phenomenon', 'phenomenum']],
            ['photos', 'photo'],
            ['pianos', 'piano'],
            ['plateaux', 'plateau'],
            ['poppies', 'poppy'],
            ['prices', ['prex', 'prix', 'price']],
            ['quizzes', 'quiz'],
            ['radii', 'radius'],
            ['roofs', 'roof'],
            ['roses', ['ros', 'rose', 'rosis']],
            ['sandwiches', ['sandwich', 'sandwiche']],
            ['scarves', ['scarf', 'scarve', 'scarff']],
            ['schemas', 'schema'], //schemata
            ['selfies', 'selfie'],
            ['series', 'series'],
            ['services', 'service'],
            ['sheriffs', 'sheriff'],
            ['shoes', ['sho', 'shoe']],
            ['spies', 'spy'],
            ['staves', ['staf', 'stave', 'staff']],
            ['stories', 'story'],
            ['strata', ['straton', 'stratum']],
            ['suitcases', ['suitcas', 'suitcase', 'suitcasis']],
            ['syllabi', 'syllabus'],
            ['tags', 'tag'],
            ['teeth', 'tooth'],
            ['theses', ['thes', 'these', 'thesis']],
            ['thieves', ['thief', 'thieve', 'thieff']],
            ['trees', ['tre', 'tree']],
            ['waltzes', ['waltz', 'waltze']],
            ['wives', 'wife'],

            // test casing: if the first letter was uppercase, it should remain so
            ['Men', 'Man'],
            ['GrandChildren', 'GrandChild'],
            ['SubTrees', ['SubTre', 'SubTree']],

            // Known issues
            //['insignia', 'insigne'],
            //['insignias', 'insigne'],
            //['rattles', 'rattle'],
        ];
    }

    public function pluralizeProvider()
    {
        // see http://english-zone.com/spelling/plurals.html
        // see http://www.scribd.com/doc/3271143/List-of-100-Irregular-Plural-Nouns-in-English
        return array(
            array('access', 'accesses'),
            array('address', 'addresses'),
            array('agenda', 'agendas'),
            array('alumnus', 'alumni'),
            array('analysis', 'analyses'),
            array('antenna', 'antennas'), //antennae
            array('appendix', array('appendicies', 'appendixes')),
            array('arch', 'arches'),
            array('atlas', 'atlases'),
            array('axe', 'axes'),
            array('baby', 'babies'),
            array('bacterium', 'bacteria'),
            array('base', 'bases'),
            array('batch', 'batches'),
            array('beau', array('beaus', 'beaux')),
            array('bee', 'bees'),
            array('box', array('bocies', 'boxes')),
            array('boy', 'boys'),
            array('bureau', array('bureaus', 'bureaux')),
            array('bus', 'buses'),
            array('bush', 'bushes'),
            array('calf', array('calfs', 'calves')),
            array('car', 'cars'),
            array('cassette', 'cassettes'),
            array('cave', 'caves'),
            array('chateau', array('chateaus', 'chateaux')),
            array('cheese', 'cheeses'),
            array('child', 'children'),
            array('circus', 'circuses'),
            array('cliff', 'cliffs'),
            array('committee', 'committees'),
            array('crisis', 'crises'),
            array('criteria', 'criterion'),
            array('cup', 'cups'),
            array('data', 'data'),
            array('day', 'days'),
            array('disco', 'discos'),
            array('device', 'devices'),
            array('drive', 'drives'),
            array('driver', 'drivers'),
            array('dwarf', array('dwarfs', 'dwarves')),
            array('echo', 'echoes'),
            array('elf', array('elfs', 'elves')),
            array('emphasis', 'emphases'),
            array('fax', array('facies', 'faxes')),
            array('feedback', 'feedback'),
            array('focus', 'foci'),
            array('foot', 'feet'),
            array('formula', 'formulas'), //formulae
            array('fungus', 'fungi'),
            array('garage', 'garages'),
            array('goose', 'geese'),
            array('half', array('halfs', 'halves')),
            array('hat', 'hats'),
            array('hero', 'heroes'),
            array('hippopotamus', 'hippopotami'), //hippopotamuses
            array('hoax', 'hoaxes'),
            array('hoof', array('hoofs', 'hooves')),
            array('house', 'houses'),
            array('index', array('indicies', 'indexes')),
            array('ion', 'ions'),
            array('iris', 'irises'),
            array('kiss', 'kisses'),
            array('knife', 'knives'),
            array('lamp', 'lamps'),
            array('leaf', array('leafs', 'leaves')),
            array('life', 'lives'),
            array('louse', 'lice'),
            array('man', 'men'),
            array('matrix', array('matricies', 'matrixes')),
            array('mouse', 'mice'),
            array('move', 'moves'),
            array('movie', 'movies'),
            array('nebula', 'nebulae'),
            array('neurosis', 'neuroses'),
            array('news', 'news'),
            array('oasis', 'oases'),
            array('objective', 'objectives'),
            array('ox', 'oxen'),
            array('party', 'parties'),
            array('person', array('persons', 'people')),
            array('phenomenon', 'phenomena'),
            array('photo', 'photos'),
            array('piano', 'pianos'),
            array('plateau', array('plateaus', 'plateaux')),
            array('poppy', 'poppies'),
            array('price', 'prices'),
            array('quiz', 'quizzes'),
            array('radius', 'radii'),
            array('roof', array('roofs', 'rooves')),
            array('rose', 'roses'),
            array('sandwich', 'sandwiches'),
            array('scarf', array('scarfs', 'scarves')),
            array('schema', 'schemas'), //schemata
            array('selfie', 'selfies'),
            array('series', 'series'),
            array('service', 'services'),
            array('sheriff', 'sheriffs'),
            array('shoe', 'shoes'),
            array('spy', 'spies'),
            array('staff', 'staves'),
            array('story', 'stories'),
            array('stratum', 'strata'),
            array('suitcase', 'suitcases'),
            array('syllabus', 'syllabi'),
            array('tag', 'tags'),
            array('thief', array('thiefs', 'thieves')),
            array('tooth', 'teeth'),
            array('tree', 'trees'),
            array('waltz', 'waltzes'),
            array('wife', 'wives'),

            // test casing: if the first letter was uppercase, it should remain so
            array('Man', 'Men'),
            array('GrandChild', 'GrandChildren'),
            array('SubTree', 'SubTrees'),
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

    /**
     * @dataProvider pluralizeProvider
     */
    public function testPluralize($plural, $singular)
    {
        $single = Inflector::pluralize($plural);
        if (is_string($singular) && is_array($single)) {
            $this->fail("--- Expected\n`string`: ".$singular."\n+++ Actual\n`array`: ".implode(', ', $single));
        } elseif (is_array($singular) && is_string($single)) {
            $this->fail("--- Expected\n`array`: ".implode(', ', $singular)."\n+++ Actual\n`string`: ".$single);
        }

        $this->assertEquals($singular, $single);
    }
}
