<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Tests\Fixtures\Integration;

use Doctrine\ORM\Mapping as ORM;
use Nano\DtoJsonEntityFieldBundle\Dto\AbstractEntityFieldDto;

#[ORM\Entity]
#[ORM\Table(name: 'orders')]
final class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'dto_json', nullable: true)]
    private ?AbstractEntityFieldDto $payload = null;

    public function changePayload(?AbstractEntityFieldDto $payload): void
    {
        $this->payload = $payload;
    }

    public function payload(): ?AbstractEntityFieldDto
    {
        return $this->payload;
    }
}
