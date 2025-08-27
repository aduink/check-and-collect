<?php

namespace Adu\CheckAndCollect\Exception;

class NoCredentialsException extends CCException
{

    public function describe(): string
    {
        return "Sie haben noch keine Credentials in den Einstellungen gesetzt";
    }
}