<?php

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\Isin;
use Symfony\Component\Validator\Constraints\IsinValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class IsinValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new IsinValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Isin());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Isin());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidIsin
     */
    public function testValidIsin($isin)
    {
        $this->validator->validate($isin, new Isin());
        $this->assertNoViolation();
    }

    public function getValidIsin()
    {
        return [
            ['XS2125535901'], 	// Goldman Sachs International	MTN	HKD HKD	126d
            ['XS2125543244'], 	// Goldman Sachs International	MTN	USD USD	1y 4d
            ['DE000HZ8VA77'], 	// UniCredit Bank AG	Bond	EUR EUR	94d
            ['CH0528261156'], 	// Leonteq Securities AG [Guernsey]	Bond	GBP GBP	3y
            ['XS2075660048'], 	// Credit Suisse International [Milan]	Bond	HKD HKD	31d
            ['XS2076647408'], 	// Credit Suisse International [Milan]	Bond	USD USD	125d
            ['XS2076680102'], 	// Credit Suisse International [Milan]	Bond	USD USD	187d
            ['XS2076709364'], 	// Credit Suisse International [Milan]	Bond	HKD HKD	96d
            ['XS2076921589'], 	// Credit Suisse International [Milan]	Bond	USD USD	125d
            ['XS2154346642'], 	// Sanctuary Capital Plc	Bond	GBP GBP	30y
            ['XS2155328524'], 	// Toyota Financial Services (UK) PLC	CP	GBP GBP	3d
            ['XS2155359081'], 	// HSBC Bank plc	MTN	USD USD	2y 2d
            ['XS2155366375'], 	// RWE AG	CP	EUR EUR	10d
            ['XS2155487593'], 	// Tasmanian Public Finance Corporation	CP	USD USD	30d
            ['XS2155665792'], 	// Agricultural Bank of China Ltd [Macao]	CD	USD USD	91d
            ['XS2155673119'], 	// Orbian Financial Services XVI LLC	CP	GBP GBP	357d
            ['XS2155687507'], 	// Orbian Financial Services III, LLC	CP	USD USD	281d
            ['XS2155798163'], 	// Toronto-Dominion Bank	MTN	USD USD	1y 134d
            ['XS2064679835'], 	// Goldman Sachs International	MTN	HKD HKD	129d
            ['XS1336064321'], 	// Morgan Stanley and Co. LLC	MTN	JPY JPY	10y 1d
            ['XS2087039439'], 	// BNP Paribas Issuance B.V.	MTN	GBP GBP	4y 358d
            ['XS2088949438'], 	// BNP Paribas SA [Hong Kong]	MTN	HKD HKD	190d
            ['XS2139137512'], 	// UBS AG [London]	Bond	USD USD	1y 3d
            ['XS2139413566'], 	// UBS AG [London]	Bond	AUD AUD	1y
            ['XS2112710350'], 	// SG Issuer S.A.	MTN	JPY JPY	28d
            ['XS2112741744'], 	// SG Issuer S.A.	MTN	AUD AUD	1y 4d
            ['XS2123606944'], 	// JP Morgan Structured Products BV	MTN	HKD HKD	30d
            ['XS2125535810'], 	// Goldman Sachs International	MTN	HKD HKD	30d
            ['XS2125543160'], 	// Goldman Sachs International	MTN	USD USD	187d
            ['XS2125792973'], 	// Goldman Sachs International	MTN	USD USD	95d
            ['CH0528261099'], 	// Leonteq Securities AG [Guernsey]	Bond	EUR EUR	3y
            ['XS2075477278'], 	// Lagoon Park Capital SA	MTN	GBP GBP	152d
            ['XS2076647150'], 	// Credit Suisse International [Milan]	Bond	USD USD	63d
            ['XS2076679518'], 	// Credit Suisse International [Milan]	Bond	USD USD	34d
            ['XS2076708044'], 	// Credit Suisse International [Milan]	Bond	AUD AUD	279d
            ['XS2076920003'], 	// Credit Suisse International [Milan]	Bond	JPY JPY	1y 1d
            ['XS2151619868'], 	// Neijiang Investment Holding Group Co. Ltd	Bond	USD USD	3y
            ['XS2155328441'], 	// Chesham Finance Ltd	CP	EUR EUR	3d
            ['XS2155358943'], 	// CSI Financial Products Limited	MTN	USD USD	364d
            ['XS2155366292'], 	// China Construction Bank Corporation [Singapore]	CD	USD USD	91d
            ['XS2155487247'], 	// Toyota Motor Finance (Netherlands) BV	CP	EUR EUR	30d
            ['XS2155664472'], 	// Mitsubishi Corporation Finance Plc	CP	EUR EUR	14d
            ['XS2155673036'], 	// Orbian Financial Services VI Ltd	CP	USD USD	87d
            ['XS2155687416'], 	// Orbian Financial Services III, LLC	CP	USD USD	267d
            ['XS2155798080'], 	// LMA SA (Liquidites de Marche SA)	CP	GBP GBP	7d
            ['XS2064677466'], 	// Goldman Sachs International	MTN	USD USD	31d
            ['XS1336064248'], 	// Morgan Stanley and Co. LLC	MTN	JPY JPY	10y 1d
            ['XS2087039355'], 	// BNP Paribas Issuance B.V.	MTN	USD USD	3y
            ['XS2088949354'], 	// BNP Paribas SA [Hong Kong]	MTN	HKD HKD	29d
            ['XS2139136977'], 	// UBS AG [London]	Bond	USD USD	29d
            ['XS2139399476'], 	// UBS AG [London]	Bond	USD USD	2y
            ['XS2112709931'], 	// SG Issuer S.A.	MTN	JPY JPY	1y 3d
            ['XS2112718767'], 	// SG Issuer S.A.	Note	EUR EUR	1y 364d
            ['XS2123606860'], 	// JP Morgan Structured Products BV	MTN	HKD HKD	30d
            ['XS2125535653'], 	// Goldman Sachs International	MTN	HKD HKD	31d
            ['XS2125543087'], 	// Goldman Sachs International	MTN	USD USD	63d
            ['XS2125792890'], 	// Goldman Sachs International	MTN	JPY JPY	33d
            ['CH0528261081'], 	// Leonteq Securities AG [Guernsey]	Bond	USD USD	3y
            ['XS2073420338'], 	// Lagoon Park Capital SA	MTN	USD USD	34d
            ['XS2076646855'], 	// Credit Suisse International [Milan]	Bond	USD USD	95d
            ['XS2076679435'], 	// Credit Suisse International [Milan]	Bond	USD USD	125d
            ['XS2076707822'], 	// Credit Suisse International [Milan]	Bond	HKD HKD	98d
            ['XS2076919765'], 	// Credit Suisse International [Milan]	Bond	USD USD	187d
            ['XS2151069775'], 	// Lloyds Bank Corporate Markets Plc	MTN	EUR EUR	3y 364d
            ['XS2155323053'], 	// Halkin Finance Plc	CP	EUR EUR	3d
            ['XS2155358869'], 	// Agence Centrale de Organismes de Securite Sociale	CP	USD USD	91d
            ['XS2155366029'], 	// Itau BBA International Plc	MTN	USD USD	364d
            ['XS2155487163'], 	// LMA SA (Liquidites de Marche SA)	CP	EUR EUR	3d
            ['XS2155629210'], 	// HSBC Bank plc	MTN	USD USD	91d
            ['XS2155672905'], 	// Orbian Financial Services XXV Ltd	CP	GBP GBP	101d
            ['XS2155687333'], 	// Orbian Financial Services III, LLC	CP	USD USD	207d
            ['XS2155707628'], 	// Korea Development Bank	MTN	USD USD	3y
            ['XS2064677110'], 	// Goldman Sachs International	MTN	USD USD	31d
            ['XS1336064164'], 	// Morgan Stanley and Co. LLC	MTN	JPY JPY	10y 1d
            ['XS2087039272'], 	// BNP Paribas Issuance B.V.	MTN	USD USD	1y 10d
            ['XS2088949271'], 	// BNP Paribas SA [Hong Kong]	MTN	HKD HKD	30d
            ['XS2139132711'], 	// UBS AG [London]	Bond	AUD AUD	190d
            ['XS2139390418'], 	// UBS AG [London]	Bond	USD USD	29d
            ['XS2112709691'], 	// SG Issuer S.A.	MTN	HKD HKD	313d
            ['XS2112717876'], 	// SG Issuer S.A.	Note	EUR EUR	1y 364d
            ['XS2123606605'], 	// JP Morgan Structured Products BV	MTN	JPY JPY	2y 3d
            ['XS2125535497'], 	// Goldman Sachs International	MTN	HKD HKD	95d
            ['XS2125542949'], 	// Goldman Sachs International	MTN	USD USD	95d
            ['XS2125674007'], 	// Goldman Sachs International	MTN	USD USD	15y
            ['CH0524351191'], 	// Leonteq Securities AG [Guernsey]	Bond	USD USD	3y
            ['XS2073290707'], 	// Marex Financial	Note	USD USD	84d
            ['XS2076646772'], 	// Credit Suisse International [Milan]	Bond	HKD HKD	279d
            ['XS2076678114'], 	// Credit Suisse International [Milan]	Bond	AUD AUD	185d
            ['XS2076706188'], 	// Credit Suisse International [Milan]	Bond	USD USD	187d
            ['XS2076919179'], 	// Credit Suisse International [Milan]	Bond	SGD SGD	187d
            ['XS2147109438'], 	// Citigroup Global Markets Funding Luxembourg SCA	MTN	USD USD	61d
            ['XS2155322675'], 	// Industrial and Commercial Bank of China Ltd	CD	USD USD	91d
            ['XS2155358513'], 	// BBVA Global Markets B.V.	MTN	USD USD	4y 361d
            ['XS2155365997'], 	// Itau BBA International Plc	MTN	USD USD	1y 120d
            ['XS2155486942'], 	// Grenke Finance Plc	MTN	EUR EUR	5y 91d
            ['XS2155629137'], 	// HSBC Bank plc	MTN	SGD SGD	91d
            ['XS2155672814'], 	// OP Corporate Bank plc	CP	GBP GBP	93d
            ['XS2155687259'], 	// Orbian Financial Services III, LLC	CP	USD USD	206d
            ['XS2155696672'], 	// Sheffield Receivables Company LLC	CP	EUR EUR	7d//Vatican City State
        ];
    }

    /**
     * @dataProvider getIsinWithInvalidLenghFormat
     */
    public function testIsinWithInvalidFormat($isin)
    {
        $this->assertViolationRaised($isin, Isin::INVALID_LENGTH_ERROR);
    }

    public function getIsinWithInvalidLenghFormat()
    {
        return [
            ['X'],
            ['XS'],
            ['XS2'],
            ['XS21'],
            ['XS215'],
            ['XS2155'],
            ['XS21556'],
            ['XS215569'],
            ['XS2155696'],
            ['XS21556966'],
            ['XS215569667'],
        ];
    }

    /**
     * @dataProvider getIsinWithInvalidPattern
     */
    public function testIsinWithInvalidPattern($isin)
    {
        $this->assertViolationRaised($isin, Isin::INVALID_PATTERN_ERROR);
    }

    public function getIsinWithInvalidPattern()
    {
        return [
            ['X12155696679'],
            ['123456789101'],
            ['XS215569667E'],
            ['XS215E69667A'],
        ];
    }

    /**
     * @dataProvider getIsinWithValidFormatButIncorrectChecksum
     */
    public function testIsinWithValidFormatButIncorrectChecksum($isin)
    {
        $this->assertViolationRaised($isin, Isin::INVALID_CHECKSUM_ERROR);
    }

    public function getIsinWithValidFormatButIncorrectChecksum()
    {
        return [
            ['XS2112212144'],
            ['DE013228VA77'],
            ['CH0512361156'],
            ['XS2125660123'],
            ['XS2012587408'],
            ['XS2012380102'],
            ['XS2012239364'],
        ];
    }

    private function assertViolationRaised($isin, $code)
    {
        $constraint = new Isin([
            'message' => 'myMessage',
        ]);

        $this->validator->validate($isin, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$isin.'"')
            ->setCode($code)
            ->assertRaised();
    }
}
