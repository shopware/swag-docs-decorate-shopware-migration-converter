<?php declare(strict_types=1);

namespace SwagMigrationExtendConverterExample\Profile\Shopware\Converter;

use Shopware\Core\Framework\Context;
use SwagMigrationAssistant\Migration\Converter\ConverterInterface;
use SwagMigrationAssistant\Migration\Converter\ConvertStruct;
use SwagMigrationAssistant\Migration\Logging\LoggingServiceInterface;
use SwagMigrationAssistant\Migration\Mapping\MappingServiceInterface;
use SwagMigrationAssistant\Migration\Media\MediaFileServiceInterface;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Profile\Shopware\Converter\ProductConverter;
use SwagMigrationExtendConverterExample\Profile\Shopware\Premapping\ManufacturerReader;

class Shopware55DecoratedProductConverter extends ProductConverter
{
    /**
     * @var ConverterInterface
     */
    private $originalProductConverter;

    public function __construct(
        ConverterInterface $originalProductConverter,
        MappingServiceInterface $mappingService,
        LoggingServiceInterface $loggingService,
        MediaFileServiceInterface $mediaFileService
    ) {
        parent::__construct($mappingService, $loggingService, $mediaFileService);
        $this->originalProductConverter = $originalProductConverter;
    }

    public function supports(MigrationContextInterface $migrationContext): bool
    {
        return $this->originalProductConverter->supports($migrationContext);
    }

    public function getSourceIdentifier(array $data): string
    {
        return $this->originalProductConverter->getSourceIdentifier($data);
    }

    public function getMediaUuids(array $converted): ?array
    {
        return $this->originalProductConverter->getMediaUuids($converted);
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
