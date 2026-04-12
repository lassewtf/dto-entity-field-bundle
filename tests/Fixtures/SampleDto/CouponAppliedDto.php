<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Tests\Fixtures\SampleDto;

use Nano\DtoJsonEntityFieldBundle\Attribute\EntityFieldDtoType;
use Nano\DtoJsonEntityFieldBundle\Dto\AbstractEntityFieldDto;

#[EntityFieldDtoType('coupon_applied')]
final readonly class CouponAppliedDto extends AbstractEntityFieldDto
{
    public function __construct(
        string $instanceUuid,
        public string $code,
    ) {
        parent::__construct($instanceUuid);
    }
}
