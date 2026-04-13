<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Doctrine\Type;

use Nano\DtoJsonEntityFieldBundle\Codec\DtoJsonCodecInterface;
use Psr\Container\ContainerInterface;

final class DtoJsonTypeRuntime
{
    private static ?ContainerInterface $container = null;
    private static ?DtoJsonCodecInterface $codec = null;

    private function __construct()
    {
    }

    public static function setContainer(ContainerInterface $container): void
    {
        self::$container = $container;
    }

    public static function setCodec(DtoJsonCodecInterface $codec): void
    {
        self::$codec = $codec;
    }

    public static function codec(): DtoJsonCodecInterface
    {
        if (null !== self::$codec) {
            return self::$codec;
        }

        if (null === self::$container) {
            throw new \LogicException('The dto_json runtime container has not been initialized yet.');
        }

        $codec = self::$container->get('nano_dto_json_entity_field.runtime_codec');
        \assert($codec instanceof DtoJsonCodecInterface);
        self::$codec = $codec;

        return self::$codec;
    }

    public static function reset(): void
    {
        self::$container = null;
        self::$codec = null;
    }
}
