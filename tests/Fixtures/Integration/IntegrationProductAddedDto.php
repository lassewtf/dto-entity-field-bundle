<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Tests\Fixtures\Integration;

use Nano\DtoJsonEntityFieldBundle\Attribute\EntityFieldDtoType;
use Nano\DtoJsonEntityFieldBundle\Dto\AbstractEntityFieldDto;

#[EntityFieldDtoType('integration_product_added')]
final readonly class IntegrationProductAddedDto extends AbstractEntityFieldDto
{
    public function __construct(
        string $instanceUuid,
        public string $sku,
        public int $quantity,
    ) {
        parent::__construct($instanceUuid);
    }
}
