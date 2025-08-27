<?php

namespace Adu\CheckAndCollect\Model;

use Adu\CheckAndCollect\Service\AduConfig;
use Adu\CheckAndCollect\Service\ApiService;
use Adu\CheckAndCollect\Service\Logger;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ServiceLocator
{
    private static ?ServiceLocator $instance = null;

    public function __construct(
        public ?ApiService       $ccApi,
        public ?SessionInterface $ccSession,
        public Logger            $ccLogger,
        public EntityRepository  $ccRepo,
        public AduConfig         $ccConfig,
        public CartService       $ccCart,
    )
    {
        self::$instance = $this;
    }

    public static function getInstance(): ?ServiceLocator
    {
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
