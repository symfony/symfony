<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\Iban;
use Symfony\Component\Validator\Constraints\IbanValidator;
use Symfony\Component\Validator\Validation;

class IbanValidatorTest extends AbstractConstraintValidatorTest
{
    protected function getApiVersion()
    {
        return Validation::API_VERSION_2_5;
    }

    protected function createValidator()
    {
        return new IbanValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Iban());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Iban());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidIbans
     */
    public function testValidIbans($iban)
    {
        $this->validator->validate($iban, new Iban());

        $this->assertNoViolation();
    }

    public function getValidIbans()
    {
        return array(
            array('CH9300762011623852957'), // Switzerland without spaces
            array('CH93  0076 2011 6238 5295 7'), // Switzerland with multiple spaces

            //Country list
            //http://www.rbs.co.uk/corporate/international/g0/guide-to-international-business/regulatory-information/iban/iban-example.ashx

            array('AL47 2121 1009 0000 0002 3569 8741'), //Albania
            array('AD12 0001 2030 2003 5910 0100'), //Andorra
            array('AT61 1904 3002 3457 3201'), //Austria
            array('AZ21 NABZ 0000 0000 1370 1000 1944'), //Azerbaijan
            array('BH67 BMAG 0000 1299 1234 56'), //Bahrain
            array('BE62 5100 0754 7061'), //Belgium
            array('BA39 1290 0794 0102 8494'), //Bosnia and Herzegovina
            array('BG80 BNBG 9661 1020 3456 78'), //Bulgaria
            array('HR12 1001 0051 8630 0016 0'), //Croatia
            array('CY17 0020 0128 0000 0012 0052 7600'), //Cyprus
            array('CZ65 0800 0000 1920 0014 5399'), //Czech Republic
            array('DK50 0040 0440 1162 43'), //Denmark
            array('EE38 2200 2210 2014 5685'), //Estonia
            array('FO97 5432 0388 8999 44'), //Faroe Islands
            array('FI21 1234 5600 0007 85'), //Finland
            array('FR14 2004 1010 0505 0001 3M02 606'), //France
            array('GE29 NB00 0000 0101 9049 17'), //Georgia
            array('DE89 3704 0044 0532 0130 00'), //Germany
            array('GI75 NWBK 0000 0000 7099 453'), //Gibraltar
            array('GR16 0110 1250 0000 0001 2300 695'), //Greece
            array('GL56 0444 9876 5432 10'), //Greenland
            array('HU42 1177 3016 1111 1018 0000 0000'), //Hungary
            array('IS14 0159 2600 7654 5510 7303 39'), //Iceland
            array('IE29 AIBK 9311 5212 3456 78'), //Ireland
            array('IL62 0108 0000 0009 9999 999'), //Israel
            array('IT40 S054 2811 1010 0000 0123 456'), //Italy
            array('LV80 BANK 0000 4351 9500 1'), //Latvia
            array('LB62 0999 0000 0001 0019 0122 9114'), //Lebanon
            array('LI21 0881 0000 2324 013A A'), //Liechtenstein
            array('LT12 1000 0111 0100 1000'), //Lithuania
            array('LU28 0019 4006 4475 0000'), //Luxembourg
            array('MK072 5012 0000 0589 84'), //Macedonia
            array('MT84 MALT 0110 0001 2345 MTLC AST0 01S'), //Malta
            array('MU17 BOMM 0101 1010 3030 0200 000M UR'), //Mauritius
            array('MD24 AG00 0225 1000 1310 4168'), //Moldova
            array('MC93 2005 2222 1001 1223 3M44 555'), //Monaco
            array('ME25 5050 0001 2345 6789 51'), //Montenegro
            array('NL39 RABO 0300 0652 64'), //Netherlands
            array('NO93 8601 1117 947'), //Norway
            array('PK36 SCBL 0000 0011 2345 6702'), //Pakistan
            array('PL60 1020 1026 0000 0422 7020 1111'), //Poland
            array('PT50 0002 0123 1234 5678 9015 4'), //Portugal
            array('RO49 AAAA 1B31 0075 9384 0000'), //Romania
            array('SM86 U032 2509 8000 0000 0270 100'), //San Marino
            array('SA03 8000 0000 6080 1016 7519'), //Saudi Arabia
            array('RS35 2600 0560 1001 6113 79'), //Serbia
            array('SK31 1200 0000 1987 4263 7541'), //Slovak Republic
            array('SI56 1910 0000 0123 438'), //Slovenia
            array('ES80 2310 0001 1800 0001 2345'), //Spain
            array('SE35 5000 0000 0549 1000 0003'), //Sweden
            array('CH93 0076 2011 6238 5295 7'), //Switzerland
            array('TN59 1000 6035 1835 9847 8831'), //Tunisia
            array('TR33 0006 1005 1978 6457 8413 26'), //Turkey
            array('AE07 0331 2345 6789 0123 456'), //UAE
            array('GB 12 CPBK 0892 9965 0449 91'), //United Kingdom

            //Extended country list
            //http://www.nordea.com/Our+services/International+products+and+services/Cash+Management/IBAN+countries/908462.html
            array('AO06000600000100037131174'), //Angola
            array('AZ21NABZ00000000137010001944'), //Azerbaijan
            array('BH29BMAG1299123456BH00'), //Bahrain
            array('BJ11B00610100400271101192591'), //Benin
            array('VG96VPVG0000012345678901'), //British Virgin Islands
            array('BF1030134020015400945000643'), //Burkina Faso
            array('BI43201011067444'), //Burundi
            array('CM2110003001000500000605306'), //Cameroon
            array('CV64000300004547069110176'), //Cape Verde
            array('FR7630007000110009970004942'), //Central African Republic
            array('CG5230011000202151234567890'), //Congo
            array('CR0515202001026284066'), //Costa Rica
            array('DO28BAGR00000001212453611324'), //Dominican Republic
            array('GT82TRAJ01020000001210029690'), //Guatemala
            array('IR580540105180021273113007'), //Iran
            array('IL620108000000099999999'), //Israel
            array('CI05A00060174100178530011852'), //Ivory Coast
            array('KZ176010251000042993'), //Kazakhstan
            array('KW74NBOK0000000000001000372151'), //Kuwait
            array('LB30099900000001001925579115'), //Lebanon
            array('MG4600005030010101914016056'), //Madagascar
            array('ML03D00890170001002120000447'), //Mali
            array('MR1300012000010000002037372'), //Mauritania
            array('MU17BOMM0101101030300200000MUR'), //Mauritius
            array('MZ59000100000011834194157'), //Mozambique
            array('PS92PALS000000000400123456702'), //Palestinian Territory
            array('PT50000200000163099310355'), //Sao Tome and Principe
            array('SA0380000000608010167519'), //Saudi Arabia
            array('SN12K00100152000025690007542'), //Senegal
            array('TN5914207207100707129648'), //Tunisia
            array('TR330006100519786457841326'), //Turkey
            array('AE260211000000230064016'), //United Arab Emirates
        );
    }

    /**
     * @dataProvider getInvalidIbans
     */
    public function testInvalidIbans($iban, $code)
    {
        $constraint = new Iban(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate($iban, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$iban.'"')
            ->setCode($code)
            ->assertRaised();
    }

    public function getInvalidIbans()
    {
        return array(
            array('CH93 0076 2011 6238 5295', Iban::CHECKSUM_FAILED_ERROR),
            array('CH930076201162385295', Iban::CHECKSUM_FAILED_ERROR),
            array('GB29 RBOS 6016 1331 9268 19', Iban::CHECKSUM_FAILED_ERROR),
            array('CH930072011623852957', Iban::CHECKSUM_FAILED_ERROR),
            array('NL39 RASO 0300 0652 64', Iban::CHECKSUM_FAILED_ERROR),
            array('NO93 8601117 947', Iban::CHECKSUM_FAILED_ERROR),
            array('CY170020 128 0000 0012 0052 7600', Iban::CHECKSUM_FAILED_ERROR),
            array('foo', Iban::TOO_SHORT_ERROR),
            array('123', Iban::TOO_SHORT_ERROR),
            array('0750447346', Iban::INVALID_COUNTRY_CODE_ERROR),
            array('CH930076201162385295]', Iban::INVALID_CHARACTERS_ERROR),

            //Ibans with lower case values are invalid
            array('Ae260211000000230064016', Iban::INVALID_CASE_ERROR),
            array('ae260211000000230064016', Iban::INVALID_CASE_ERROR),
        );
    }
}
