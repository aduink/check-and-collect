<?php

namespace Adu\CheckAndCollect\Exception;

class InvalidResponse extends CCException
{
    public function describe(): string
    {
        return "Die Response ist invalid ($this->message)";
    }
}