<?php

namespace Adu\CheckAndCollect\Exception;

class WrongProductRequested extends CCException
{

    public function describe(): string
    {
        return "Wahrscheinlich wurde eine B2c anfrage für eine b2b entität getätigt";
    }
}