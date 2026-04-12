<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Tests\Fixtures\Integration;

use Doctrine\ORM\Mapping as ORM;
use Nano\DtoJsonEntityFieldBundle\Dto\AbstractEntityFieldDto;

#[ORM\Entity]
#[ORM\Table(name: 'typed_orders')]
final class TypedOrder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?AbstractEntityFieldDto $payload = null;
}
