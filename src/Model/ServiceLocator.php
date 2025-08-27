<?php
namespace Adu\CheckAndCollect\Model;

class ServiceLocator
{
    private static $instance;
    private function __construct()
    {
    }

    public static function getInstance(): ServiceLocator
    {
        if (!self::$instance) {
            self::$instance = new ServiceLocator();
        }

        return self::$instance;
    }

    public function get(string $service): mixed
    {
        return $this->$service ?? NULL;

    }

    public function set($name, $service): void
    {
        $this->$name = $service;
    }
}
