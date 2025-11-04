<?php

namespace Adu\CheckAndCollect\Model;

use Adu\CheckAndCollect\Service\AduConfig;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;

class Scoring implements \JsonSerializable
{
    private ?string $customErr = null;
    private ?CustomerAddressEntity $address = null;
    private \DateTimeInterface $ts;

    public function __construct(private array $apiResponse, private float $defaultScore, ?CustomerAddressEntity $address = null)
    {
        $this->ts = new \DateTime('today');
        $this->address = $address;
    }
    public function setAddress(CustomerAddressEntity $address): void
    {
        $this->address = $address;
    }

    public function setCustomErr(string $error): self
    {
        $this->customErr = $error;
        return $this;
    }

    public function getScore(): float
    {
        if (empty($this->apiResponse['score'])) {
            return $this->defaultScore;
        }
        return (float)$this->apiResponse['score'] / 10;
    }

    public function getResponse(): array
    {
        return $this->apiResponse;
    }

    public function getInfo(): ?string
    {
        return $this->apiResponse['result']['resultData']['identification']['text'] ?? null;
    }
    public function setInfo(string $info): self {
        $this->apiResponse['result']['resultData']['identification']['text'] ??= $info;
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->apiResponse['result']['resultData']['identification']['code'] ?? null;
    }
    public function setCode(string $code): self {
        $this->apiResponse['result']['resultData']['identification']['code'] ??= $code;
        return $this;
    }

    public function getError(): ?string
    {
        if ($this->customErr) {
            return $this->customErr;
        }
        if (!isset($this->apiResponse['result']['resultData']['identification'])) {
            return "Api hat keinen Info text mitgeliefert";
        }
        if (!isset($this->apiResponse['score'])) {
            return "Api hat keinen Score mitgeliefert";
        }
        if (!$this->apiResponse['score']) {
            return "Api hat einen 0er Score zurückgeliefert";
        }
        return null;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        $ret = [
            AduConfig::SCORE => $this->getScore(),
            AduConfig::ADDITIONAL => $this->getInfo(),
            AduConfig::AWARENESS => $this->getCode(),
            AduConfig::LAST_SCORE_TIME => $this->ts->format("Y-m-d\TH:i:s"),
        ];
        $err = $this->getError();
        if ($err) {
            $ret['error'] = $err;
        }
        if($this->address !== null){
            $ret[AduConfig::CHECKED_ADDRESS_ID] = $this->address->getId();
        }
        return $ret;
    }
}
