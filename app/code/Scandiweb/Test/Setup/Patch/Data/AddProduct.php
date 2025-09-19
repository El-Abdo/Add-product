<?php

declare(strict_types=1);

namespace Scandiweb\Test\Setup\Patch\Data;

use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\App\State;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Model\Product;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Store\Model\StoreManagerInterface;

class AddProduct implements DataPatchInterface
{
    protected array $sourceItems = [];

    public function __construct(
        protected ModuleDataSetupInterface        $moduleDataSetup,
        protected ProductInterfaceFactory         $productInterfaceFactory,
        protected EavSetup                        $eavSetup,
        protected StoreManagerInterface           $storeManager,
        protected ProductRepositoryInterface      $productRepository,
        protected State                           $appState,
        protected SourceItemInterfaceFactory      $sourceItemFactory,
        protected SourceItemsSaveInterface        $sourceItemsSaveInterface,
        protected CategoryLinkManagementInterface $categoryLink,
        protected CategoryRepositoryInterface     $categoryRepository

    )
    {

    }

    public function apply()
    {
        $this->appState->emulateAreaCode('adminhtml', [$this, 'execute']);

    }

    /**
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws ValidationException
     */
    public function execute()
    {
        $this->moduleDataSetup->getConnection()->startSetup();


        $sku = 'scandiweb-test-product';
        $product = $this->productInterfaceFactory->create();

        // Check if product already exists
        if ($product->getIdBySku($sku)) {
            return;
        }

        $attributeSetId = $this->eavSetup->getAttributeSetId(Product::ENTITY, 'Default');
        $websiteIDs = [$this->storeManager->getStore()->getWebsiteId()];
        $product->setTypeId(Type::TYPE_SIMPLE)
            ->setWebsiteIds($websiteIDs)
            ->setAttributeSetId($attributeSetId)
            ->setName('Scandiweb test product')
            ->setUrlKey('testproduct')
            ->setSku($sku)
            ->setPrice(9.99)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 1, 'is_qty_decimal' => 0, 'is_in_stock' => 1]);

        $savedProduct = $this->productRepository->save($product);

        $sourceItem = $this->sourceItemFactory->create();
        $sourceItem->setSourceCode('default');
        $sourceItem->setQuantity(10);
        $sourceItem->setSku($product->getSku());
        $sourceItem->setStatus(SourceItemInterface::STATUS_IN_STOCK);
        $this->sourceItems[] = $sourceItem;

        $this->sourceItemsSaveInterface->execute($this->sourceItems);

        $this->categoryLink->assignProductToCategories($savedProduct->getSku(), [11]);


        $this->moduleDataSetup->getConnection()->endSetup();
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
