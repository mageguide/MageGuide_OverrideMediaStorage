<?php

namespace MageGuide\OverrideMediaStorage\Service;

use Magento\MediaStorage\Service\ImageResize;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product\Image\ParamsBuilder;
use Magento\Catalog\Model\View\Asset\ImageFactory as AssertImageFactory;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Filesystem;
use Magento\Framework\Image;
use Magento\Framework\Image\Factory as ImageFactory;
use Magento\Catalog\Model\Product\Media\ConfigInterface as MediaConfig;
use Magento\Framework\App\State;
use Magento\Framework\View\ConfigInterface as ViewConfig;
use \Magento\Catalog\Model\ResourceModel\Product\Image as ProductImage;
use Magento\Theme\Model\Config\Customization as ThemeCustomizationConfig;
use Magento\Theme\Model\ResourceModel\Theme\Collection;
use Magento\Framework\App\Filesystem\DirectoryList;

class ImageResizeOverride extends ImageResize {

    private $appState;
    private $imageConfig;
    private $productImage;
    private $imageFactory;
    private $paramsBuilder;
    private $viewConfig;
    private $assertImageFactory;
    private $themeCustomizationConfig;
    private $themeCollection;
    private $mediaDirectory;
    private $filesystem;

    public function __construct(
        State $appState,
        MediaConfig $imageConfig,
        ProductImage $productImage,
        ImageFactory $imageFactory,
        ParamsBuilder $paramsBuilder,
        ViewConfig $viewConfig,
        AssertImageFactory $assertImageFactory,
        ThemeCustomizationConfig $themeCustomizationConfig,
        Collection $themeCollection,
        Filesystem $filesystem
    ) {
        $this->appState = $appState;
        $this->imageConfig = $imageConfig;
        $this->productImage = $productImage;
        $this->imageFactory = $imageFactory;
        $this->paramsBuilder = $paramsBuilder;
        $this->viewConfig = $viewConfig;
        $this->assertImageFactory = $assertImageFactory;
        $this->themeCustomizationConfig = $themeCustomizationConfig;
        $this->themeCollection = $themeCollection;
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->filesystem = $filesystem;

        parent::__construct($this->appState, $this->imageConfig, $this->productImage, $this->imageFactory, $this->paramsBuilder, $this->viewConfig, $this->assertImageFactory, $this->themeCustomizationConfig, $this->themeCollection, $this->filesystem);
    }

	/**
     * Create resized images of different sizes from themes for specific product ids
     * @param array $product_ids
     * @param array|null $themes
     * @return \Generator
     * @throws NotFoundException
     */
    public function resizeFromThemesProductIds(array $product_ids, array $themes = null): \Generator
    {
    	/* test for product ids with log
    	$testArray = [1,2,3,4,5,6,7,8,9,10];
    	$originalImageName = 'images';
    	$count = $this->productImage->testImages($product_ids);
    	foreach ($testArray as $test) {
    		yield $originalImageName => $count;
    	}
    	*/

        $count = $this->productImage->getCountAllProductImagesProductIds($product_ids);

        if (!$count) {
            throw new NotFoundException(__('Cannot resize images - product images not found'));
        }

        $productImages = $this->productImage->getAllProductImagesProductIds($product_ids);
        $viewImages = $this->getViewImages($themes ?? $this->getThemesInUse());

        foreach ($productImages as $image) {
            $originalImageName = $image['filepath'];
            $originalImagePath = $this->mediaDirectory->getAbsolutePath(
                $this->imageConfig->getMediaPath($originalImageName)
            );
            foreach ($viewImages as $viewImage) {
                $this->resize($viewImage, $originalImagePath, $originalImageName);
            }
            yield $originalImageName => $count;
        }
    }

    /**
     * Search the current theme
     * @return array
     */
    private function getThemesInUse(): array
    {
        $themesInUse = [];
        $registeredThemes = $this->themeCollection->loadRegisteredThemes();
        $storesByThemes = $this->themeCustomizationConfig->getStoresByThemes();
        $keyType = is_integer(key($storesByThemes)) ? 'getId' : 'getCode';
        foreach ($registeredThemes as $registeredTheme) {
            if (array_key_exists($registeredTheme->$keyType(), $storesByThemes)) {
                $themesInUse[] = $registeredTheme;
            }
        }
        return $themesInUse;
    }

    /**
     * Get view images data from themes
     * @param array $themes
     * @return array
     */
    private function getViewImages(array $themes): array
    {
        $viewImages = [];
        /** @var \Magento\Theme\Model\Theme $theme */
        foreach ($themes as $theme) {
            $config = $this->viewConfig->getViewConfig([
                'area' => Area::AREA_FRONTEND,
                'themeModel' => $theme,
            ]);
            $images = $config->getMediaEntities('Magento_Catalog', ImageHelper::MEDIA_TYPE_CONFIG_NODE);
            foreach ($images as $imageId => $imageData) {
                $uniqIndex = $this->getUniqueImageIndex($imageData);
                $imageData['id'] = $imageId;
                $viewImages[$uniqIndex] = $imageData;
            }
        }
        return $viewImages;
    }

    /**
     * Get unique image index
     * @param array $imageData
     * @return string
     */
    private function getUniqueImageIndex(array $imageData): string
    {
        ksort($imageData);
        unset($imageData['type']);
        return md5(json_encode($imageData));
    }

    /**
     * Make image
     * @param string $originalImagePath
     * @param array $imageParams
     * @return Image
     */
    private function makeImage(string $originalImagePath, array $imageParams): Image
    {
        $image = $this->imageFactory->create($originalImagePath);
        $image->keepAspectRatio($imageParams['keep_aspect_ratio']);
        $image->keepFrame($imageParams['keep_frame']);
        $image->keepTransparency($imageParams['keep_transparency']);
        $image->constrainOnly($imageParams['constrain_only']);
        $image->backgroundColor($imageParams['background']);
        $image->quality($imageParams['quality']);
        return $image;
    }

    /**
     * Resize image
     * @param array $viewImage
     * @param string $originalImagePath
     * @param string $originalImageName
     */
    private function resize(array $viewImage, string $originalImagePath, string $originalImageName)
    {
        $imageParams = $this->paramsBuilder->build($viewImage);
        $image = $this->makeImage($originalImagePath, $imageParams);
        $imageAsset = $this->assertImageFactory->create(
            [
                'miscParams' => $imageParams,
                'filePath' => $originalImageName,
            ]
        );

        if ($imageParams['image_width'] !== null && $imageParams['image_height'] !== null) {
            $image->resize($imageParams['image_width'], $imageParams['image_height']);
        }
        $image->save($imageAsset->getPath());
    }

}