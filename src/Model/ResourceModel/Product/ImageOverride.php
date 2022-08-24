<?php

namespace MageGuide\OverrideMediaStorage\Model\ResourceModel\Product;

use Magento\Catalog\Model\ResourceModel\Product\Image;
use Magento\Catalog\Model\ResourceModel\Product\Gallery;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Query\Generator;
use Magento\Framework\DB\Select;
use Magento\Framework\App\ResourceConnection;

class ImageOverride extends Image
{
    private $connection;
    private $batchQueryGenerator;
    private $resourceConnection;
    private $batchSize;

    public function __construct(
        Generator $generator,
        ResourceConnection $resourceConnection,
        $batchSize = 100
    ) {
        $this->batchQueryGenerator = $generator;
        $this->resourceConnection = $resourceConnection;
        $this->connection = $this->resourceConnection->getConnection();
        $this->batchSize = $batchSize;

        parent::__construct($this->batchQueryGenerator, $this->resourceConnection, $this->batchSize);
    }

    /**
     * Returns product images
     *
     * @return \Generator
     */
    public function getAllProductImagesProductIds(array $product_ids): \Generator
    {
        $batchSelectIterator = $this->batchQueryGenerator->generate(
            'value_id',
            $this->getVisibleImagesSelectProductIds($product_ids),
            $this->batchSize,
            \Magento\Framework\DB\Query\BatchIteratorInterface::NON_UNIQUE_FIELD_ITERATOR
        );

        foreach ($batchSelectIterator as $select) {
            foreach ($this->connection->fetchAll($select) as $key => $value) {
                yield $key => $value;
            }
        }
    }

    /**
     * Get the number of unique pictures of products
     * @return int
     */
    public function getCountAllProductImagesProductIds(array $product_ids): int
    {
        $select = $this->getVisibleImagesSelectProductIds($product_ids)->reset('columns')->columns('count(*)');
        return (int) $this->connection->fetchOne($select);
    }

    public function testImages(array $product_ids): int
    {
        $writer = new \Zend\Log\Writer\Stream(BP.'/var/log/images-test.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);

        $select = $this->getVisibleImagesSelectProductIds($product_ids);
        $return_select = $this->connection->fetchAll($select);
        foreach ($return_select as $image) {
            $originalImageName = $image['filepath'];
            $logger->info('image filepath: ' . $originalImageName);
        }

        $select = $this->getVisibleImagesSelectProductIds($product_ids)->reset('columns')->columns('count(*)');
        $return_count = (int) $this->connection->fetchOne($select);
        $logger->info('count images: ' . $return_count);

        return $return_count;
    }

    /**
     * @return Select
     */
    private function getVisibleImagesSelectProductIds(array $product_ids): Select
    {
        return $this->connection->select()->distinct()
            ->from(
                ['images' => $this->resourceConnection->getTableName(Gallery::GALLERY_TABLE)],
                'value as filepath'
            )->where(
                'disabled = 0'
            )->join(
                ['images_products' => $this->resourceConnection->getTableName(Gallery::GALLERY_VALUE_TO_ENTITY_TABLE)],
                'images.value_id=images_products.value_id'
            )->where(
                'images_products.entity_id IN (?)', $product_ids
            );
    }
}
