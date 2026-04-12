<?php

declare(strict_types=1);

use Doctrine\ORM\Mapping\ChainTypedFieldMapper;
use Nano\DtoJsonEntityFieldBundle\Codec\DtoJsonCodec;
use Nano\DtoJsonEntityFieldBundle\Codec\DtoJsonCodecInterface;
use Nano\DtoJsonEntityFieldBundle\Doctrine\TypedFieldMapper\EntityFieldDtoTypedFieldMapper;
use Nano\DtoJsonEntityFieldBundle\Envelope\DtoJsonEnvelopeFactory;
use Nano\DtoJsonEntityFieldBundle\Registry\EntityFieldDtoRegistry;
use Nano\DtoJsonEntityFieldBundle\Registry\EntityFieldDtoRegistryInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->private();

    $services
        ->set(DtoJsonEnvelopeFactory::class)
        ->arg('$version', param('nano_dto_json_entity_field.envelope.version'));

    $services
        ->set(EntityFieldDtoRegistry::class)
        ->arg('$tagToClass', param('nano_dto_json_entity_field.registry.mappings'));

    $services->alias(EntityFieldDtoRegistryInterface::class, EntityFieldDtoRegistry::class);

    $services
        ->set('nano_dto_json_entity_field.normalizer.object', ObjectNormalizer::class);

    $services
        ->set('nano_dto_json_entity_field.serializer', Serializer::class)
        ->args([
            [service('nano_dto_json_entity_field.normalizer.object')],
            [],
        ]);

    $services
        ->set(DtoJsonCodec::class)
        ->arg('$normalizer', service('nano_dto_json_entity_field.serializer'))
        ->arg('$denormalizer', service('nano_dto_json_entity_field.serializer'))
        ->arg('$registry', service(EntityFieldDtoRegistryInterface::class))
        ->arg('$envelopeFactory', service(DtoJsonEnvelopeFactory::class));

    $services->alias(DtoJsonCodecInterface::class, DtoJsonCodec::class);
    $services->alias('nano_dto_json_entity_field.runtime_codec', DtoJsonCodec::class)->public();

    $services->set(EntityFieldDtoTypedFieldMapper::class);

    $services
        ->set('nano_dto_json_entity_field.doctrine.chain_typed_field_mapper', ChainTypedFieldMapper::class)
        ->args([
            service('doctrine.orm.typed_field_mapper.default'),
            service(EntityFieldDtoTypedFieldMapper::class),
        ]);
};
