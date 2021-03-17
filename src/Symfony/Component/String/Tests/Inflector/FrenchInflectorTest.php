<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\String\Tests\Inflector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Inflector\FrenchInflector;

class FrenchInflectorTest extends TestCase
{
    public function pluralizeProvider()
    {
        return [
            //Le pluriel par défaut
            ['voiture', 'voitures'],
            //special characters
            ['œuf', 'œufs'],
            ['oeuf', 'oeufs'],

            //Les mots finissant par s, x, z sont invariables en nombre
            ['bois', 'bois'],
            ['fils', 'fils'],
            ['héros', 'héros'],
            ['nez', 'nez'],
            ['rictus', 'rictus'],
            ['souris', 'souris'],
            ['tas', 'tas'],
            ['toux', 'toux'],

            //Les mots finissant en eau prennent tous un x au pluriel
            ['eau', 'eaux'],
            ['sceau', 'sceaux'],

            //Les mots finissant en au prennent tous un x au pluriel sauf landau
            ['noyau', 'noyaux'],
            ['landau', 'landaus'],

            //Les mots finissant en eu prennent un x au pluriel sauf pneu, bleu et émeu
            ['pneu', 'pneus'],
            ['bleu', 'bleus'],
            ['émeu', 'émeus'],
            ['cheveu', 'cheveux'],

            //Les mots finissant en al se terminent en aux au pluriel
            ['amiral', 'amiraux'],
            ['animal', 'animaux'],
            ['arsenal', 'arsenaux'],
            ['bocal', 'bocaux'],
            ['canal', 'canaux'],
            ['capital', 'capitaux'],
            ['caporal', 'caporaux'],
            ['cheval', 'chevaux'],
            ['cristal', 'cristaux'],
            ['général', 'généraux'],
            ['hopital', 'hopitaux'],
            ['hôpital', 'hôpitaux'],
            ['idéal', 'idéaux'],
            ['journal', 'journaux'],
            ['littoral', 'littoraux'],
            ['local', 'locaux'],
            ['mal', 'maux'],
            ['métal', 'métaux'],
            ['minéral', 'minéraux'],
            ['principal', 'principaux'],
            ['radical', 'radicaux'],
            ['terminal', 'terminaux'],

            //sauf bal, carnaval, caracal, chacal, choral, corral, étal, festival, récital et val
            ['bal', 'bals'],
            ['carnaval', 'carnavals'],
            ['caracal', 'caracals'],
            ['chacal', 'chacals'],
            ['choral', 'chorals'],
            ['corral', 'corrals'],
            ['étal', 'étals'],
            ['festival', 'festivals'],
            ['récital', 'récitals'],
            ['val', 'vals'],

            // Les noms terminés en -ail prennent un s au pluriel.
            ['portail', 'portails'],
            ['rail', 'rails'],

            // SAUF aspirail, bail, corail, émail, fermail, soupirail, travail, vantail et vitrail qui font leur pluriel en -aux
            ['aspirail', 'aspiraux'],
            ['bail', 'baux'],
            ['corail', 'coraux'],
            ['émail', 'émaux'],
            ['fermail', 'fermaux'],
            ['soupirail', 'soupiraux'],
            ['travail', 'travaux'],
            ['vantail', 'vantaux'],
            ['vitrail', 'vitraux'],

            // Les noms terminés en -ou prennent un s au pluriel.
            ['trou', 'trous'],
            ['fou', 'fous'],

            //SAUF Bijou, caillou, chou, genou, hibou, joujou et pou qui prennent un x au pluriel
            ['bijou', 'bijoux'],
            ['caillou', 'cailloux'],
            ['chou', 'choux'],
            ['genou', 'genoux'],
            ['hibou', 'hiboux'],
            ['joujou', 'joujoux'],
            ['pou', 'poux'],

            //Inflected word
            ['cinquante', 'cinquante'],
            ['soixante', 'soixante'],
            ['mille', 'mille'],

            //Titles
            ['monsieur', 'messieurs'],
            ['madame', 'mesdames'],
            ['mademoiselle', 'mesdemoiselles'],
            ['monseigneur', 'messeigneurs'],
        ];
    }

    /**
     * @dataProvider pluralizeProvider
     */
    public function testSingularize(string $singular, string $plural)
    {
        $this->assertSame([$singular], (new FrenchInflector())->singularize($plural));
        // test casing: if the first letter was uppercase, it should remain so
        $this->assertSame([ucfirst($singular)], (new FrenchInflector())->singularize(ucfirst($plural)));
    }

    /**
     * @dataProvider pluralizeProvider
     */
    public function testPluralize(string $singular, string $plural)
    {
        $this->assertSame([$plural], (new FrenchInflector())->pluralize($singular));
        // test casing: if the first letter was uppercase, it should remain so
        $this->assertSame([ucfirst($plural)], (new FrenchInflector())->pluralize(ucfirst($singular)));
    }
}
