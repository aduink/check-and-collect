<?php

namespace Adu\CheckAndCollect\Exception;

class RequestFailed extends CCException
{
    public function describe(): string
    {
        return "Request ist Fehlgeschlagen";
    }
}