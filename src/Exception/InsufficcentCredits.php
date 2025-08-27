<?php

namespace Adu\CheckAndCollect\Exception;

class InsufficcentCredits extends CCException
{
    public function describe(): string
    {
        return "Sie haben nicht genügend Credits für die Boniprüfung zur Verfügung";
    }
}