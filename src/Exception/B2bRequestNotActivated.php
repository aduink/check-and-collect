<?php

namespace Adu\CheckAndCollect\Exception;

class B2bRequestNotActivated extends CCException
{

    public function describe(): string
    {
        return "Es wurde eine Businessentität angefragt welche nicht freigeschaltet ist";
    }
}