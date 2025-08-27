<?php

namespace Adu\CheckAndCollect\Exception;

class ConnectivityException extends CCException
{

    public function describe(): string
    {
        return "Externer Dienstleister kann nicht erreicht werden";
    }
}