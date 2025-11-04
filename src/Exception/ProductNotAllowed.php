<?php

namespace Adu\CheckAndCollect\Exception;

class ProductNotAllowed extends CCException
{

    public function describe(): string
    {
        return "Sie haben keine berechtigung dieses Produkt anzufragen";
    }
}