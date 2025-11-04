<?php

namespace Adu\CheckAndCollect\Exception;

class InvalidCredentials extends CCException
{

    public function describe(): string
    {
        return "Die eingetragenen Credentials konnten nicht zur authentifizierung benutzt werden";
    }
}