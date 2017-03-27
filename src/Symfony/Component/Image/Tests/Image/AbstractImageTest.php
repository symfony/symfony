<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Image;

use Symfony\Component\Image\Fixtures\Loader as FixturesLoader;
use Symfony\Component\Image\Exception\InvalidArgumentException;
use Symfony\Component\Image\Exception\RuntimeException;
use Symfony\Component\Image\Image\Box;
use Symfony\Component\Image\Image\ImageInterface;
use Symfony\Component\Image\Image\LayersInterface;
use Symfony\Component\Image\Image\Metadata\MetadataBag;
use Symfony\Component\Image\Image\Palette\CMYK;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;
use Symfony\Component\Image\Image\Palette\Grayscale;
use Symfony\Component\Image\Image\Point;
use Symfony\Component\Image\Image\Fill\Gradient\Horizontal;
use Symfony\Component\Image\Image\Point\Center;
use Symfony\Component\Image\Tests\TestCase;
use Symfony\Component\Image\Image\Palette\RGB;
use Symfony\Component\Image\Image\Profile;
use Symfony\Component\Image\Imagick\Image as ImagickImage;
use Symfony\Component\Image\Imagick\Loader as ImagickLoader;
use Symfony\Component\Image\Gmagick\Loader as GmagickLoader;

abstract class AbstractImageTest extends TestCase
{
    public function testPaletteIsRGBIfRGBImage()
    {
        $image = $this->getLoader()->open(FixturesLoader::getFixture('google.png'));
        $this->assertInstanceOf(RGB::class, $image->palette());
    }

    public function testPaletteIsCMYKIfCMYKImage()
    {
        $image = $this->getLoader()->open(FixturesLoader::getFixture('pixel-CMYK.jpg'));
        $this->assertInstanceOf(CMYK::class, $image->palette());
    }

    public function testPaletteIsGrayIfGrayImage()
    {
        $image = $this->getLoader()->open(FixturesLoader::getFixture('pixel-grayscale.jpg'));
        $this->assertInstanceOf(Grayscale::class, $image->palette());
    }

    public function testDefaultPaletteCreationIsRGB()
    {
        $image = $this->getLoader()->create(new Box(10, 10));
        $this->assertInstanceOf(RGB::class, $image->palette());
    }

    /**
     * @dataProvider providePalettes
     */
    public function testPaletteAssociatedIsRelatedToGivenColor($paletteClass, $input)
    {
        $palette = new $paletteClass();

        $image = $this
            ->getLoader()
            ->create(new Box(10, 10), $palette->color($input));

        $this->assertEquals($palette, $image->palette());
    }

    public function providePalettes()
    {
        return array(
            array(RGB::class, array(255, 0, 0)),
            array(CMYK::class, array(10, 0, 0, 0)),
            array(Grayscale::class, array(25)),
        );
    }

    /**
     * @dataProvider provideFromAndToPalettes
     */
    public function testUsePalette($from, $to, $color)
    {
        $palette = new $from();

        $image = $this
            ->getLoader()
            ->create(new Box(10, 10), $palette->color($color));

        $targetPalette = new $to();

        $image->usePalette($targetPalette);

        $this->assertEquals($targetPalette, $image->palette());
        $image->save(__DIR__ . '/tmp.jpg');

        $image = $this->getLoader()->open(__DIR__ . '/tmp.jpg');

        $this->assertInstanceOf($to, $image->palette());
        unlink(__DIR__ . '/tmp.jpg');
    }

    public function testSaveWithoutFormatShouldSaveInOriginalFormat()
    {
        if (!extension_loaded('exif')) {
            $this->markTestSkipped('The EXIF extension is required for this test');
        }

        $tmpFile = __DIR__ . '/tmpfile';

        $this
            ->getLoader()
            ->open(FixturesLoader::getFixture('large.jpg'))
            ->save($tmpFile);

        $data = exif_read_data($tmpFile);
        $this->assertEquals('image/jpeg', $data['MimeType']);
        unlink($tmpFile);
    }

    public function testSaveWithoutPathFileFromImageLoadShouldBeOkay()
    {
        $source = FixturesLoader::getFixture('google.png');
        $tmpFile = __DIR__ . '/../results/google.tmp.png';

        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }

        copy($source, $tmpFile);

        $this->assertEquals(md5_file($source), md5_file($tmpFile));

        $this
            ->getLoader()
            ->open($tmpFile)
            ->resize(new Box(20, 20))
            ->save();

        $this->assertNotEquals(md5_file($source), md5_file($tmpFile));
        unlink($tmpFile);
    }

    public function testSaveWithoutPathFileFromImageCreationShouldFail()
    {
        $image = $this->getLoader()->create(new Box(20, 20));
        $this->setExpectedException(RuntimeException::class);
        $image->save();
    }

    public function provideFromAndToPalettes()
    {
        $palettes = array(
            array(
                RGB::class,
                CMYK::class,
                array(10, 10, 10),
            ),
            array(
                RGB::class,
                Grayscale::class,
                array(10, 10, 10),
            ),
            array(
                CMYK::class,
                RGB::class,
                array(10, 10, 10, 0),
            ),
            array(
                CMYK::class,
                Grayscale::class,
                array(10, 10, 10, 0),
            ),
        );

        if (!defined('HHVM_VERSION')) {
            $palettes[] = array(
                Grayscale::class,
                RGB::class,
                array(10),
            );
            $palettes[] = array(
                Grayscale::class,
                CMYK::class,
                array(10),
            );
        }

        return $palettes;
    }

    public function testProfile()
    {
        $image = $this
            ->getLoader()
            ->create(new Box(10, 10))
            ->profile(Profile::fromPath(FixturesLoader::getFixture('ICCProfiles/Adobe/RGB/VideoHD.icc')));

        $color = $image->getColorAt(new Point(0, 0));

        $this->assertInstanceOf(RGB::class, $color->getPalette());
        $this->assertSame(255, $color->getValue(ColorInterface::COLOR_RED));
        $this->assertSame(255, $color->getValue(ColorInterface::COLOR_GREEN));
        $this->assertSame(255, $color->getValue(ColorInterface::COLOR_BLUE));
        $this->assertSame(100, $color->getAlpha());
    }

    public function testRotateWithNoBackgroundColor()
    {
        $factory = $this->getLoader();

        $image = $factory->open(FixturesLoader::getFixture('google.png'));
        $image->rotate(90);

        $size = $image->getSize();

        $this->assertSame(126, $size->getWidth());
        $this->assertSame(364, $size->getHeight());
    }

    public function testCopyResizedImageToImage()
    {
        $factory = $this->getLoader();

        $image = $factory->open(FixturesLoader::getFixture('google.png'));
        $size  = $image->getSize();

        $image = $image->paste(
            $image->copy()
                ->resize($size->scale(0.5))
                ->flipVertically(),
            new Center($size)
        );

        $this->assertSame(364, $image->getSize()->getWidth());
        $this->assertSame(126, $image->getSize()->getHeight());
    }

    /**
     * @dataProvider provideFilters
     */
    public function testResizeWithVariousFilters($filter)
    {
        $factory = $this->getLoader();
        $image = $factory->open(FixturesLoader::getFixture('google.png'));

        $image = $image->resize(new Box(30, 30), $filter);

        $this->assertSame(30, $image->getSize()->getWidth());
        $this->assertSame(30, $image->getSize()->getHeight());
    }

    public function testResizeWithInvalidFilter()
    {
        $factory = $this->getLoader();
        $image = $factory->open(FixturesLoader::getFixture('google.png'));

        $this->setExpectedException(InvalidArgumentException::class);
        $image->resize(new Box(30, 30), 'no filter');
    }

    public function provideFilters()
    {
        return array(
            array(ImageInterface::FILTER_UNDEFINED),
            array(ImageInterface::FILTER_POINT),
            array(ImageInterface::FILTER_BOX),
            array(ImageInterface::FILTER_TRIANGLE),
            array(ImageInterface::FILTER_HERMITE),
            array(ImageInterface::FILTER_HANNING),
            array(ImageInterface::FILTER_HAMMING),
            array(ImageInterface::FILTER_BLACKMAN),
            array(ImageInterface::FILTER_GAUSSIAN),
            array(ImageInterface::FILTER_QUADRATIC),
            array(ImageInterface::FILTER_CUBIC),
            array(ImageInterface::FILTER_CATROM),
            array(ImageInterface::FILTER_MITCHELL),
            array(ImageInterface::FILTER_LANCZOS),
            array(ImageInterface::FILTER_BESSEL),
            array(ImageInterface::FILTER_SINC),
        );
    }

    public function testThumbnailShouldReturnACopy()
    {
        $factory = $this->getLoader();

        $image = $factory->open(FixturesLoader::getFixture('google.png'));
        $thumbnail = $image->thumbnail(new Box(20, 20));

        $this->assertNotSame($image, $thumbnail);
    }

    public function testThumbnailWithInvalidModeShouldThrowAnException()
    {
        $factory = $this->getLoader();
        $image = $factory->open(FixturesLoader::getFixture('google.png'));
        $this->setExpectedException(InvalidArgumentException::class, 'Invalid mode specified');
        $image->thumbnail(new Box(20, 20), "boumboum");
    }

    public function testResizeShouldReturnTheImage()
    {
        $factory = $this->getLoader();

        $image = $factory->open(FixturesLoader::getFixture('google.png'));
        $resized = $image->resize(new Box(20, 20));

        $this->assertSame($image, $resized);
    }

    /**
     * @dataProvider provideDimensionsAndModesForThumbnailGeneration
     */
    public function testThumbnailGeneration($sourceW, $sourceH, $thumbW, $thumbH, $mode, $expectedW, $expectedH)
    {
        $factory = $this->getLoader();
        $image   = $factory->create(new Box($sourceW, $sourceH));
        $inset   = $image->thumbnail(new Box($thumbW, $thumbH), $mode);

        $size = $inset->getSize();

        $this->assertEquals($expectedW, $size->getWidth());
        $this->assertEquals($expectedH, $size->getHeight());
    }

    public function provideDimensionsAndModesForThumbnailGeneration()
    {
        return array(
            // landscape with smaller portrait
            array(320, 240, 32, 48, ImageInterface::THUMBNAIL_INSET, 32, round(32 * 240 / 320)),
            array(320, 240, 32, 48, ImageInterface::THUMBNAIL_OUTBOUND, 32, 48),
            // landscape with smaller landscape
            array(320, 240, 32, 16, ImageInterface::THUMBNAIL_INSET, round(16 * 320 / 240), 16),
            array(320, 240, 32, 16, ImageInterface::THUMBNAIL_OUTBOUND, 32, 16),

            // portait with smaller portrait
            array(240, 320, 24, 48, ImageInterface::THUMBNAIL_INSET, 24, round(24 * 320 / 240)),
            array(240, 320, 24, 48, ImageInterface::THUMBNAIL_OUTBOUND, 24, 48),
            // portait with smaller landscape
            array(240, 320, 24, 16, ImageInterface::THUMBNAIL_INSET, round(16 * 240 / 320), 16),
            array(240, 320, 24, 16, ImageInterface::THUMBNAIL_OUTBOUND, 24, 16),

            // landscape with larger portrait
            array(32, 24, 320, 300, ImageInterface::THUMBNAIL_INSET, 32, 24),
            array(32, 24, 320, 300, ImageInterface::THUMBNAIL_OUTBOUND, 32, 24),
            // landscape with larger landscape
            array(32, 24, 320, 200, ImageInterface::THUMBNAIL_INSET, 32, 24),
            array(32, 24, 320, 200, ImageInterface::THUMBNAIL_OUTBOUND, 32, 24),

            // portait with larger portrait
            array(24, 32, 240, 300, ImageInterface::THUMBNAIL_INSET, 24, 32),
            array(24, 32, 240, 300, ImageInterface::THUMBNAIL_OUTBOUND, 24, 32),
            // portait with larger landscape
            array(24, 32, 240, 400, ImageInterface::THUMBNAIL_INSET, 24, 32),
            array(24, 32, 240, 400, ImageInterface::THUMBNAIL_OUTBOUND, 24, 32),

            // landscape with intersect portrait
            array(320, 240, 340, 220, ImageInterface::THUMBNAIL_INSET, round(220 * 320 / 240), 220),
            array(320, 240, 340, 220, ImageInterface::THUMBNAIL_OUTBOUND, 320, 220),
            // landscape with intersect portrait
            array(320, 240, 300, 360, ImageInterface::THUMBNAIL_INSET, 300, round(300 / 320 * 240)),
            array(320, 240, 300, 360, ImageInterface::THUMBNAIL_OUTBOUND, 300, 240),
        );
    }

    public function testThumbnailGenerationToDimensionsLergestThanSource()
    {
        $test_image = FixturesLoader::getFixture('google.png');
        $test_image_width = 364;
        $test_image_height = 126;
        $width = $test_image_width + 1;
        $height = $test_image_height + 1;

        $factory = $this->getLoader();
        $image   = $factory->open($test_image);
        $size = $image->getSize();

        $this->assertEquals($test_image_width, $size->getWidth());
        $this->assertEquals($test_image_height, $size->getHeight());

        $inset   = $image->thumbnail(new Box($width, $height), ImageInterface::THUMBNAIL_INSET);
        $size = $inset->getSize();
        unset($inset);

        $this->assertEquals($test_image_width, $size->getWidth());
        $this->assertEquals($test_image_height, $size->getHeight());

        $outbound = $image->thumbnail(new Box($width, $height), ImageInterface::THUMBNAIL_OUTBOUND);
        $size = $outbound->getSize();
        unset($outbound);
        unset($image);

        $this->assertEquals($test_image_width, $size->getWidth());
        $this->assertEquals($test_image_height, $size->getHeight());
    }

    public function testCropResizeFlip()
    {
        $factory = $this->getLoader();

        $image = $factory->open(FixturesLoader::getFixture('google.png'))
            ->crop(new Point(0, 0), new Box(126, 126))
            ->resize(new Box(200, 200))
            ->flipHorizontally();

        $size = $image->getSize();

        unset($image);

        $this->assertEquals(200, $size->getWidth());
        $this->assertEquals(200, $size->getHeight());
    }

    public function testCreateAndSaveEmptyImage()
    {
        $factory = $this->getLoader();

        $palette = new RGB();

        $image   = $factory->create(new Box(400, 300), $palette->color('000'));

        $size  = $image->getSize();

        unset($image);

        $this->assertEquals(400, $size->getWidth());
        $this->assertEquals(300, $size->getHeight());
    }

    public function testCreateTransparentGradient()
    {
        $factory = $this->getLoader();

        $palette = new RGB();

        $size    = new Box(100, 50);
        $image   = $factory->create($size, $palette->color('f00'));

        $image->paste(
                $factory->create($size, $palette->color('ff0'))
                    ->applyMask(
                        $factory->create($size)
                            ->fill(
                                new Horizontal(
                                    $image->getSize()->getWidth(),
                                    $palette->color('fff'),
                                    $palette->color('000')
                                )
                            )
                    ),
                new Point(0, 0)
            );

        $size = $image->getSize();

        unset($image);

        $this->assertEquals(100, $size->getWidth());
        $this->assertEquals(50, $size->getHeight());
    }

    public function testMask()
    {
        $factory = $this->getLoader();

        $image = $factory->open(FixturesLoader::getFixture('google.png'));

        $image->applyMask($image->mask())
            ->save(__DIR__.'/../results/mask.png');

        $size = $factory->open(__DIR__.'/../results/mask.png')
            ->getSize();

        $this->assertEquals(364, $size->getWidth());
        $this->assertEquals(126, $size->getHeight());

        unlink(__DIR__.'/../results/mask.png');
    }

    public function testColorHistogram()
    {
        $factory = $this->getLoader();

        $image = $factory->open(FixturesLoader::getFixture('google.png'));

        $this->assertEquals(6438, count($image->histogram()));
    }

    public function testImageResolutionChange()
    {
        $loader = $this->getLoader();
        $image = $loader->open(FixturesLoader::getFixture('resize/210-design-19933.jpg'));
        $outfile = __DIR__.'/../results/reduced.jpg';
        $image->save($outfile, array(
            'resolution-units' => ImageInterface::RESOLUTION_PIXELSPERINCH,
            'resolution-x' => 144,
            'resolution-y' => 144
        ));

        if ($loader instanceof ImagickLoader) {
            $i = new \Imagick($outfile);
            $info = $i->identifyimage();
            $this->assertEquals(144, $info['resolution']['x']);
            $this->assertEquals(144, $info['resolution']['y']);
        }
        if ($loader instanceof GmagickLoader) {
            $i = new \Gmagick($outfile);
            $info = $i->getimageresolution();
            $this->assertEquals(144, $info['x']);
            $this->assertEquals(144, $info['y']);
        }

        unlink($outfile);
    }

    public function testInOutResult()
    {
        $this->processInOut("trans", "png","png");
        $this->processInOut("trans", "png","gif");
        $this->processInOut("trans", "png","jpg");
        $this->processInOut("anima", "gif","png");
        $this->processInOut("anima", "gif","gif");
        $this->processInOut("anima", "gif","jpg");
        $this->processInOut("trans", "gif","png");
        $this->processInOut("trans", "gif","gif");
        $this->processInOut("trans", "gif","jpg");
        $this->processInOut("large", "jpg","png");
        $this->processInOut("large", "jpg","gif");
        $this->processInOut("large", "jpg","jpg");
    }

    public function testLayerReturnsALayerInterface()
    {
        $factory = $this->getLoader();

        $image = $factory->open(FixturesLoader::getFixture('google.png'));

        $this->assertInstanceOf(LayersInterface::class, $image->layers());
    }

    public function testCountAMonoLayeredImage()
    {
        $this->assertEquals(1, count($this->getMonoLayeredImage()->layers()));
    }

    public function testCountAMultiLayeredImage()
    {
        if (!$this->supportMultipleLayers()) {
            $this->markTestSkipped('This driver does not support multiple layers');
        }

        $this->assertGreaterThan(1, count($this->getMultiLayeredImage()->layers()));
    }

    public function testLayerOnMonoLayeredImage()
    {
        foreach ($this->getMonoLayeredImage()->layers() as $layer) {
            $this->assertInstanceOf(ImageInterface::class, $layer);
            $this->assertCount(1, $layer->layers());
        }
    }

    public function testLayerOnMultiLayeredImage()
    {
        foreach ($this->getMultiLayeredImage()->layers()  as $layer) {
            $this->assertInstanceOf(ImageInterface::class, $layer);
            $this->assertCount(1, $layer->layers());
        }
    }

    public function testChangeColorSpaceAndStripImage()
    {
        $color = $this
            ->getLoader()
            ->open(FixturesLoader::getFixture('pixel-CMYK.jpg'))
            ->usePalette(new RGB())
            ->strip()
            ->getColorAt(new Point(0, 0));

        $this->assertEquals('#0082a2', (string) $color);
    }

    public function testStripImageWithInvalidProfile()
    {
        $image = $this
            ->getLoader()
            ->open(FixturesLoader::getFixture('invalid-icc-profile.jpg'));

        $color = $image->getColorAt(new Point(0, 0));
        $image->strip();
        $afterColor = $image->getColorAt(new Point(0, 0));

        $this->assertEquals((string) $color, (string) $afterColor);
    }

    public function testGetColorAt()
    {
        $color = $this
            ->getLoader()
            ->open(FixturesLoader::getFixture('65-percent-black.png'))
            ->getColorAt(new Point(0, 0));

        $this->assertEquals('#000000', (string) $color);
        $this->assertFalse($color->isOpaque());
        $this->assertEquals('65', $color->getAlpha());
    }

    public function testGetColorAtGrayScale()
    {
        $color = $this
            ->getLoader()
            ->open(FixturesLoader::getFixture('pixel-grayscale.jpg'))
            ->getColorAt(new Point(0, 0));

        $this->assertEquals('#4d4d4d', (string) $color);
        $this->assertTrue($color->isOpaque());
    }

    public function testGetColorAtCMYK()
    {
        $color = $this
            ->getLoader()
            ->open(FixturesLoader::getFixture('pixel-CMYK.jpg'))
            ->getColorAt(new Point(0, 0));

        $this->assertEquals('cmyk(98%, 0%, 30%, 23%)', (string) $color);
        $this->assertTrue($color->isOpaque());
    }

    public function testGetColorAtOpaque()
    {
        $color = $this
            ->getLoader()
            ->open(FixturesLoader::getFixture('100-percent-black.png'))
            ->getColorAt(new Point(0, 0));

        $this->assertEquals('#000000', (string) $color);
        $this->assertTrue($color->isOpaque());

        $this->assertSame(0, $color->getRed());
        $this->assertSame(0, $color->getGreen());
        $this->assertSame(0, $color->getBlue());
    }

    public function testStripGBRImageHasGoodColors()
    {
        $color = $this
            ->getLoader()
            ->open(FixturesLoader::getFixture('pixel-GBR.jpg'))
            ->strip()
            ->getColorAt(new Point(0, 0));

        $this->assertEquals('#d07560', (string) $color);
    }

    // Test whether a simple action such as resizing a GIF works
    // Using the original animated GIF and a slightly more complex one as reference
    // anima2.gif courtesy of Cyndi Norrie (http://cyndipop.tumblr.com/) via 15 Folds (http://15folds.com)
    public function testResizeAnimatedGifResizeResult()
    {
        if (!$this->supportMultipleLayers()) {
            $this->markTestSkipped('This driver does not support multiple layers');
        }

        $loader = $this->getLoader();

        $image = $loader->open(FixturesLoader::getFixture('anima.gif'));

        // Imagick requires the images to be coalesced first!
        if ($image instanceof ImagickImage) {
            $image->layers()->coalesce();
        }

        foreach ($image->layers() as $frame) {
            $frame->resize(new Box(121, 124));
        }

        $image->save(__DIR__.'/../results/anima-half-size.gif', array('animated' => true));
        @unlink(__DIR__.'/../results/anima-half-size.gif');

        $image = $loader->open(FixturesLoader::getFixture('anima2.gif'));

        // Imagick requires the images to be coalesced first!
        if ($image instanceof ImagickImage) {
            $image->layers()->coalesce();
        }

        foreach ($image->layers() as $frame) {
            $frame->resize(new Box(200, 144));
        }

        $target = __DIR__.'/../results/anima2-half-size.gif';
        $image->save($target, array('animated' => true));

        $this->assertFileExists($target);

        @unlink($target);
    }

    public function testMetadataReturnsMetadataInstance()
    {
        $this->assertInstanceOf(MetadataBag::class, $this->getMonoLayeredImage()->metadata());
    }

    public function testCloningImageResultsInNewMetadataInstance()
    {
        $image = $this->getMonoLayeredImage();
        $originalMetadata = $image->metadata();
        $clone = clone $image;
        $this->assertNotSame($originalMetadata, $clone->metadata(), 'The image\'s metadata is the same after cloning the image, but must be a new instance.');
    }

    public function testImageSizeOnAnimatedGif()
    {
        $loader = $this->getLoader();

        $image = $loader->open(FixturesLoader::getFixture('anima3.gif'));

        $size = $image->getSize();

        $this->assertEquals(300, $size->getWidth());
        $this->assertEquals(200, $size->getHeight());
    }

    /**
     * @dataProvider provideVariousSources
     */
    public function testResolutionOnSave($source)
    {
        $file = __DIR__ . '/test-resolution.jpg';

        $image = $this->getLoader()->open($source);
        $image->save($file, array(
            'resolution-units' => ImageInterface::RESOLUTION_PIXELSPERINCH,
            'resolution-x' => 150,
            'resolution-y' => 120,
            'resampling-filter' => ImageInterface::FILTER_LANCZOS,
        ));

        $saved = $this->getLoader()->open($file);
        $this->assertEquals(array('x' => 150, 'y' => 120), $this->getImageResolution($saved));
        unlink($file);
    }

    public function provideVariousSources()
    {
        return array(
            array(FixturesLoader::getFixture('example.svg')),
            array(FixturesLoader::getFixture('100-percent-black.png')),
        );
    }

    public function testFillAlphaPrecision()
    {
        $loader = $this->getLoader();
        $palette = new RGB();
        $image = $loader->create(new Box(1, 1), $palette->color("#f00"));
        $fill = new Horizontal(100, $palette->color("#f00", 17), $palette->color("#f00", 73));
        $image->fill($fill);

        $actualColor = $image->getColorAt(new Point(0, 0));
        $this->assertEquals(17, $actualColor->getAlpha());
    }

    public function testImageCreatedAlpha()
    {
        $palette = new RGB();
        $image = $this->getLoader()->create(new Box(1, 1), $palette->color("#7f7f7f", 10));
        $actualColor = $image->getColorAt(new Point(0, 0));

        $this->assertEquals("#7f7f7f", (string) $actualColor);
        $this->assertEquals(10, $actualColor->getAlpha());
    }

    abstract protected function getImageResolution(ImageInterface $image);

    private function getMonoLayeredImage()
    {
        return $this->getLoader()->open(FixturesLoader::getFixture('google.png'));
    }

    private function getMultiLayeredImage()
    {
        return $this->getLoader()->open(FixturesLoader::getFixture('cat.gif'));
    }

    protected function processInOut($file, $in, $out)
    {
        $factory = $this->getLoader();
        $class = preg_replace('/\\\\/', "_", get_called_class());
        $image = $factory->open(FixturesLoader::getFixture($file.'.'.$in));
        $thumb = $image->thumbnail(new Box(50, 50), ImageInterface::THUMBNAIL_OUTBOUND);
        if (!is_dir(__DIR__.'/../results/in_out')) {
            mkdir(__DIR__.'/../results/in_out', 0777, true);
        }
        $target = __DIR__."/../results/in_out/{$class}_{$file}_from_{$in}_to.{$out}";
        $thumb->save($target);

        $this->assertFileExists($target);
        unlink($target);
    }

    /**
     * @return \Symfony\Component\Image\Image\LoaderInterface
     */
    abstract protected function getLoader();

    /**
     * @return boolean
     */
    abstract protected function supportMultipleLayers();
}
