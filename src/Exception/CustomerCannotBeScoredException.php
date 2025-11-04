<?php

namespace Adu\CheckAndCollect\Exception;

class CustomerCannotBeScoredException extends CCException
{

    public function describe(): string
    {
        return "Der Kunde kann nicht geprüft werden";
    }
}