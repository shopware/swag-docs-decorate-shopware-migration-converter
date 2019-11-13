<?php declare(strict_types=1);

namespace SwagMigrationExtendConverterExample\Profile\Shopware\Converter;

use Shopware\Core\Framework\Context;
use SwagMigrationAssistant\Migration\Converter\ConverterInterface;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\Mapping\MappingServiceInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationExtendConverterExample\Profile\Shopware\Premapping\ManufacturerReader;

class Shopware55DecoratedProductConverter implements ConverterInterface
{
    /**
     * @var ConverterInterface
     */
    private $originalProductConverter;

    /**
     * @var MappingServiceInterface
     */
    private $mappingService;

    public function __construct
    (
        ConverterInterface $originalProductConverter,
        MappingServiceInterface $mappingService
    ) {
        $this->originalProductConverter = $originalProductConverter;
        $this->mappingService = $mappingService;
    }

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $this->originalProductConverter->supports($migrationContext);
    }

    public function getSourceIdentifier(array $data): string
    {
        return $this->originalProductConverter->getSourceIdentifier($data);
    }

    public function writeMapping(Context $context): void
    {
        $this->originalProductConverter->writeMapping($context);
    }

    public function convert(
        array $data,
        Context $context,
        MigrationContextInterface $migrationContext
    ): ConvertStruct
    {
        if (!isset($data['manufacturer']['id'])) {
            return $this->originalProductConverter->convert($data, $context, $migrationContext);
        }

        $manufacturerId = $data['manufacturer']['id'];
        unset($data['manufacturer']);

        $mapping = $this->mappingService->getMapping(
            $migrationContext->getConnection()->getId(),
            ManufacturerReader::getMappingName(),
            $manufacturerId,
            $context
        );

        $convertedStruct = $this->originalProductConverter->convert($data, $context, $migrationContext);

        if ($mapping === null) {
            return $convertedStruct;
        }

        $converted = $convertedStruct->getConverted();
        $converted['manufacturerId'] = $mapping['entityUuid'];

        return new ConvertStruct($converted, $convertedStruct->getUnmapped());
    }
}