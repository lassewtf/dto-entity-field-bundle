<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Codec;

use Nano\DtoJsonEntityFieldBundle\Dto\AbstractEntityFieldDto;
use Nano\DtoJsonEntityFieldBundle\Envelope\DtoJsonEnvelopeFactory;
use Nano\DtoJsonEntityFieldBundle\Exception\InvalidDtoPayloadException;
use Nano\DtoJsonEntityFieldBundle\Registry\EntityFieldDtoRegistryInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final readonly class DtoJsonCodec implements DtoJsonCodecInterface
{
    public function __construct(
        private NormalizerInterface $normalizer,
        private DenormalizerInterface $denormalizer,
        private EntityFieldDtoRegistryInterface $registry,
        private DtoJsonEnvelopeFactory $envelopeFactory,
    ) {
    }

    public function encode(AbstractEntityFieldDto $dto): array
    {
        $normalized = $this->normalizer->normalize($dto);

        if (!\is_array($normalized)) {
            throw new InvalidDtoPayloadException('DTO normalization must return an array.');
        }

        if (\array_key_exists('_dto', $normalized)) {
            throw new InvalidDtoPayloadException('The "_dto" key is reserved for envelope metadata.');
        }

        unset($normalized['instanceUuid']);

        return $this->envelopeFactory
            ->create($this->registry->tagFor($dto::class), $dto->instanceUuid, $normalized)
            ->toArray();
    }

    public function decode(array $payload): AbstractEntityFieldDto
    {
        $envelope = $this->envelopeFactory->fromArray($payload);
        $class = $this->registry->classForTag($envelope->tag);
        $data = ['instanceUuid' => $envelope->instanceUuid, ...$envelope->data];

        try {
            $dto = $this->denormalizer->denormalize($data, $class);
        } catch (\Throwable $exception) {
            throw new InvalidDtoPayloadException(
                \sprintf('Failed to denormalize DTO for tag "%s".', $envelope->tag),
                previous: $exception,
            );
        }

        if (!$dto instanceof AbstractEntityFieldDto) {
            throw new InvalidDtoPayloadException(\sprintf(
                'Decoded DTO must extend "%s".',
                AbstractEntityFieldDto::class,
            ));
        }

        return $dto;
    }
}
