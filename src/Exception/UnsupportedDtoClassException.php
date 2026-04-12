<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Exception;

final class UnsupportedDtoClassException extends \InvalidArgumentException
{
    public function __construct(string $class)
    {
        parent::__construct(\sprintf('Unsupported DTO class "%s".', $class));
    }
}
