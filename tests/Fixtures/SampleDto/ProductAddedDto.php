<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Tests\Fixtures\SampleDto;

use Nano\DtoJsonEntityFieldBundle\Attribute\EntityFieldDtoType;
use Nano\DtoJsonEntityFieldBundle\Dto\AbstractEntityFieldDto;

#[EntityFieldDtoType('product_added')]
final readonly class ProductAddedDto extends AbstractEntityFieldDto
{
    public function __construct(
        string $instanceUuid,
        public string $sku,
        public int $quantity,
    ) {
        parent::__construct($instanceUuid);
    }

    public function withQuantity(int $quantity, string $instanceUuid): self
    {
        return new self($instanceUuid, $this->sku, $quantity);
    }
}
