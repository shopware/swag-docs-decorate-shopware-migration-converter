<?php declare(strict_types=1);

namespace SwagMigrationExtendConverterExample\Profile\Shopware\Premapping;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistryInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\AbstractPremappingReader;
use SwagMigrationAssistant\Migration\Premapping\PremappingChoiceStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingEntityStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\ProductDataSelection;
use SwagMigrationAssistant\Profile\Shopware\Gateway\ShopwareGatewayInterface;
use SwagMigrationAssistant\Profile\Shopware\ShopwareProfileInterface;

class ManufacturerReader extends AbstractPremappingReader
{
    private const MAPPING_NAME = 'swag_manufacturer';

    /**
     * @var EntityRepositoryInterface
     */
    private $manufacturerRepo;

    /**
     * @var GatewayRegistryInterface
     */
    private $gatewayRegistry;

    /**
     * @var string[]
     */
    private $preselectionDictionary;

    /**
     * @var string[]
     */
    private $preselectionSourceNameDictionary;

    public function __construct(
        EntityRepositoryInterface $manufacturerRepo,
        GatewayRegistryInterface $gatewayRegistry
    ) {
        $this->manufacturerRepo = $manufacturerRepo;
        $this->gatewayRegistry = $gatewayRegistry;
    }

    public static function getMappingName(): string
    {
        return self::MAPPING_NAME;
    }

    /**
     * Is the current profile supported and which DataSelection is supported?
     */
    public function supports(MigrationContextInterface $migrationContext, array $entityGroupNames): bool
    {
        return $migrationContext->getProfile() instanceof ShopwareProfileInterface
            && in_array(ProductDataSelection::IDENTIFIER, $entityGroupNames, true);
    }

    public function getPremapping(Context $context, MigrationContextInterface $migrationContext): PremappingStruct
    {
        $this->fillConnectionPremappingDictionary($migrationContext);
        $mapping = $this->getMapping($migrationContext);
        $choices = $this->getChoices($context);
        $this->setPreselection($mapping);

        return new PremappingStruct(self::getMappingName(), $mapping, $choices);
    }

    /**
     * Reads all manufacturers out of the source system, looks into connectionPremappingDictionary if a premapping
     * is currently set and returns the filled mapping array
     *
     * @return PremappingEntityStruct[]
     */
    private function getMapping(MigrationContextInterface $migrationContext): array
    {
        /** @var ShopwareGatewayInterface $gateway */
        $gateway = $this->gatewayRegistry->getGateway($migrationContext);

        $preMappingData = $gateway->readTable($migrationContext, 's_articles_supplier');

        $entityData = [];
        foreach ($preMappingData as $data) {
            $this->preselectionSourceNameDictionary[$data['id']] = $data['name'];

            $uuid = '';
            if (isset($this->connectionPremappingDictionary[$data['id']])) {
                $uuid = $this->connectionPremappingDictionary[$data['id']]['destinationUuid'];
            }

            $entityData[] = new PremappingEntityStruct($data['id'], $data['name'], $uuid);
        }

        return $entityData;
    }

    /**
     * Returns all choices out of the manufacturer repository
     *
     * @return PremappingChoiceStruct[]
     */
    private function getChoices(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('name'));

        /** @var ProductManufacturerEntity[] $manufacturers */
        $manufacturers = $this->manufacturerRepo->search($criteria, $context);

        $choices = [];
        foreach ($manufacturers as $manufacturer) {
            $this->preselectionDictionary[$manufacturer->getName()] = $manufacturer->getId();
            $choices[] = new PremappingChoiceStruct($manufacturer->getId(), $manufacturer->getName());
        }

        return $choices;
    }

    /**
     * Loops through mapping and sets preselection, if uuid is currently not filled
     *
     * @param PremappingEntityStruct[] $mapping
     */
    private function setPreselection(array $mapping): void
    {
        foreach ($mapping as $item) {
            if (!isset($this->preselectionSourceNameDictionary[$item->getSourceId()]) || $item->getDestinationUuid() !== '') {
                continue;
            }

            $sourceName = $this->preselectionSourceNameDictionary[$item->getSourceId()];
            $preselectionValue = $this->getPreselectionValue($sourceName);

            if ($preselectionValue !== null) {
                $item->setDestinationUuid($preselectionValue);
            }
        }
    }

    /**
     * Only a simple example how to achieve a preselection
     */
    private function getPreselectionValue(string $sourceName): ?string
    {
        $preselectionValue = null;
        $validPreselection = 'Shopware';
        $choice = 'shopware AG';

        if ($sourceName === $validPreselection && isset($this->preselectionDictionary[$choice])) {
            $preselectionValue = $this->preselectionDictionary[$choice];
        }

        return $preselectionValue;
    }
}