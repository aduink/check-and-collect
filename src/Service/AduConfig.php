<?php declare(strict_types=1);

namespace Adu\CheckAndCollect\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class AduConfig
{
    final const CHECKED_ADDRESS_ID = 'adu_checked_address_id';
    final const ADDITIONAL = 'adu_additional_value';
    final const AWARENESS = 'adu_ident_code';
    final const SCORE = 'adu_score_value';
    final const LAST_SCORE_TIME = 'adu_last_check';
    final const SKIP_CHECK = 'deny_solvencycheck_user';
    final const TEMP_BLOCK_CHECK = 'adu_temp_block_check_for_recursive_api';

    private ?string $salesChannelId = null;

    public function __construct(private readonly SystemConfigService $config)
    {
    }

    public function setSalesChannelId(?string $id): void
    {
        $this->salesChannelId = $id;
    }

    private function get(string $key, $default = null)
    {
        return $this->config->get('AduinCheckAndCollect.config.' . $key, $this->salesChannelId) ?? $default;
    }

    public function test(): bool
    {
        return $this->get('test', false);
    }

    public function maxAge(): int
    {
        return $this->get('maxAge', 10) ?: 10;
    }

    public function defaultCustomerScore(): float
    {
        return $this->get('defaultNoResult');
    }

    public function defaultCompanyScore(): float
    {
        return $this->get('defaultCompany');
    }

    public function checkCompany(): bool
    {
        return $this->get('checkCompany', false);
    }

    public function activeApi(): bool
    {
        return $this->get('activeApi', false);
    }

    public function activeLog(): bool
    {
        return $this->get('activeLog', false);
    }

    public function ipAddress(): ?string
    {
        return $this->get('ipAddress');
    }

    public function login(): string
    {
        return $this->get('login', '');
    }

    public function password(): string
    {
        return $this->get('password', '');
    }
}
