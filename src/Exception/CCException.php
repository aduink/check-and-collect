<?php

namespace Adu\CheckAndCollect\Exception;

abstract class CCException extends \Exception
{
    public static function fromCode(string $message, int $code): self{
        return match($code){
            400, 612  => new CustomerCannotBeScoredException($message, $code),
            401, 403,  => new InvalidCredentials($message, $code),
            603 => new InsufficcentCredits($message, $code),
            611 => new ProductNotAllowed($message, $code),
            619 => new WrongProductRequested($message, $code),
            default => new RequestFailed($message, $code),
        };
    }
    public abstract function describe(): string;
}