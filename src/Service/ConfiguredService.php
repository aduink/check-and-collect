<?php

namespace Adu\CheckAndCollect\Service;

use Symfony\Contracts\Service\Attribute\Required;

trait ConfiguredService
{
    private AduConfig $config;

    #[Required]
    public function setConfig(AduConfig $config): void
    {
        $this->config = $config;
    }

    public function setScope(?string $salesChannelId): void
    {
        $this->config->setSalesChannelId($salesChannelId);
    }
}