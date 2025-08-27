<?php
declare(strict_types=1);

namespace Adu\CheckAndCollect\Core\Rule;

use Adu\CheckAndCollect\Exception\B2bRequestNotActivated;
use Adu\CheckAndCollect\Exception\CustomerCannotBeScoredException;
use Adu\CheckAndCollect\Model\RatingRequest;
use Adu\CheckAndCollect\Model\Scoring;
use Adu\CheckAndCollect\Model\ServiceLocator;
use Adu\CheckAndCollect\Service\AduConfig;
use Adu\CheckAndCollect\Service\ApiService;
use Adu\CheckAndCollect\Service\ConfiguredService;
use Adu\CheckAndCollect\Service\Logger;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

trait RuleTrait
{
    use ConfiguredService;

    private ?ApiService $api;
    private Logger $logger;

    private ?SessionInterface $session;
    private CartService $cartService;
    /**
     * @var EntityRepository<CustomerEntity>
     */
    private EntityRepository $repo;
    protected string $operator;
    protected CustomerEntity $customer;

    abstract private function getCacheKey(): string;

    abstract private function getValueType(): string;

    abstract private function compare($value): bool;

    abstract private function getDefaultValue(bool $b2b): float|string;

    /**
     * @throws \Exception
     */
    abstract private function getValueFromScoring(Scoring $scoring): null|float|string;

    /**
     * @return bool
     * Rückgabe gibt an, ob alle Bedingungen für eine Bonitätsprüfung gegeben sind
     */
    private function init(RuleScope $scope): bool
    {
        $locator = ServiceLocator::getInstance();
        if($locator === null){ // Locator wurde noch nicht initialisiert
            return false;
        }
        $this->config ??= $locator->ccConfig;
        $this->api ??= $locator->ccApi;
        $this->session ??= $locator->ccSession;
        $this->logger ??= $locator->ccLogger;
        $this->repo ??= $locator->ccRepo;
        $this->cartService ??= $locator->ccCart;
        $customer = $scope->getSalesChannelContext()->getCustomer();
        if($customer === null){
            $this->logger->log("Kunde wurde noch nicht gesetzt");
            return false;
        }
        $this->customer ??= $customer;
        return true;
    }
    private function defaultMatch(bool $b2b = false): bool{
        $value = $this->getDefaultValue($b2b);
        $ret = $this->compare($value);
        $this->logger->log("Returning From default (".($ret ? "True": "False").")", true);
        return $ret;
    }
    private function tryCompare($value): ?bool {
        $expectedType = $this->getValueType();
        if (gettype($value) === $expectedType) {
            return $this->compare($value);
        }
        if ($value !== null) {
            $actual = gettype($value);
            $this->logger->log("Ein Cached Value wurde gefunden aber hat nicht den richtigen Typen. Erwartet: $expectedType => Tatsächlich: $actual", true);
        }
        return null;
    }
    private function tryFromCache(CustomerAddressEntity $address): ?bool{
        $value = $this->getCache($address);
        return $this->tryCompare($value);
    }
    private function tryNewCompare(RatingRequest $request, RuleScope $scope): bool{
        try {
            $request
                ->setAmount($this->getGoodsAmount($scope->getSalesChannelContext()))
                ->setCache(true);
            $scoring = $this->getScore($request);
            $newVal = $this->getValueFromScoring($scoring) ?? $this->getDefaultValue($request->isBusiness());
        } catch (\Throwable $e) {
            $this->logger->log("ERROR: " . $e->getMessage() . "\n\n", true);
            return $this->defaultMatch($request->isBusiness());
        }
        try {
            return $this->tryCompare($newVal) ?? true;
        } catch (\Exception $e) {
            $this->logger->log("ERROR: " . $e->getMessage() . "\n\n", true);
            return true;
        }
    }

    public function match(RuleScope $scope): bool
    {
        if (!$this->init($scope)) {
            return true; // Services wurden noch nicht gesetzt. Muss noch nicht prüfen
        }
        $this->logger->log("Rule wurde initialisiert");
        if(!$this->config->activeApi()){
            return true;
        }
        $this->logger->log("Matching: ". __CLASS__);

        try {
            $request = RatingRequest::fromCustomer($this->customer);
            $request->validate($this->config);
        }catch(B2bRequestNotActivated) {
            $this->logger->log("B2bRequest not activated", true);
            return $this->defaultMatch(true);
        }catch (CustomerCannotBeScoredException $e){
            $this->logger->log("Customer Cannot be Scored: ".$e->getMessage(), true);
            return $this->defaultMatch();
        }
        $result = $this->tryFromCache($request->address);
        if($result !== null){
            $this->logger->log("Returning From Cache: ". ($result ? "True": "False"));
            return $result;
        }

        if ($this->shoudlSkipCheck($scope)) {
            $this->logger->log("Scoring Should be skipped. Returning True");
            return true;
        }
        return $this->tryNewCompare($request, $scope);
    }

    private function getScore(RatingRequest $request): Scoring
    {
        // Prüfen
        $result = $this->api->getSolvencyCheck($request);
        $this->session?->set('adu_score_value', $result->toArray());
        $this->logger->log("Updated Session: ", context: $this->session?->get('adu_score_value') ?? []);
        return $result;
    }

    private function getLastCheckDate(array $array): ?\DateTimeInterface
    {
        $field = $array[AduConfig::LAST_SCORE_TIME] ?? null;
        if ($field === null || $field instanceof \DateTimeInterface) {
            return $field;
        }
        if (gettype($field) === 'string') {
            try {
                return new \DateTime($field);
            } catch (\Exception) {
            }
        }
        if (gettype($field) !== 'array') {
            $this->logger->log("Kann last check datum nicht als Datum interpretieren", true);
            return null;
        }
        try {
            $datestring = $field['date'];
            $tz = $field['timezone'];

            $timezone = new \DateTimeZone($tz);
            return new \DateTime($datestring, $timezone);
        } catch (\Throwable $e) {
            $this->logger->log($e->getMessage(), true);
            return null;
        }
    }

    private function getValidCustomFieldCache(CustomerAddressEntity $address, string $key): null|float|string
    {
        $customFields = $this->customer->getCustomFields();
        $value = $this->getValidCacheFromArray($customFields, $key, $address->getId());
        if(isset($value)){
            $this->logger->log("$key wird aus den Customfields bezogen", context: [$value]);
            return $value;
        }
        $this->logger->log("Customfields haben keinen $key", context: [$customFields]);
        return null;
    }

    private function getValidSessionCache(string $key): null|float|string
    {
        $array = $this->session?->get('adu_score_value');
        $value = $this->getValidCacheFromArray($array, $key);
        if(isset($value)){
            $this->logger->log("$key wird aus Session bezogen", context: [$value]);
            return $value;
        }
        $this->logger->log("Kein Cache für $key gefunden", context: [$array]);
        return null;
    }
    private function getValidCacheFromArray(?array $source, string $key, ?string $addressId = null){
        if(!$source){
            return null;
        }
        $lastCheck = $this->getLastCheckDate($source);
        if ($lastCheck && (new \DateTime())->diff($lastCheck)->days >= $this->config->maxAge()) {
            $this->logger->log("$key Value in gefunden, aber zu alt", context: [['source' =>$source, 'max_age' => $this->config->maxAge(). " Tage"]]);
            return null;
        }

        if(!empty($source[AduConfig::CHECKED_ADDRESS_ID]) && $addressId !== null){
            if ($source[AduConfig::CHECKED_ADDRESS_ID] !== $addressId) {
                $this->logger->log("Die Letzte geprüfte Adresse stimmt nicht mit der Jetzigen Adresse überein", false, [['old_source' => $source, 'new_id' => $addressId]]);
                return null;
            }
        }
        return $source[$key] ?? null;
    }

    private function getCache(CustomerAddressEntity $address): null|string|float|bool
    {
        $key = $this->getCacheKey();
        return $this->getValidSessionCache($key) ?? $this->getValidCustomFieldCache( $address, $key );
    }

    private function shoudlSkipCheck(RuleScope $scope): bool
    {
        /*
        if (!$scope instanceof CheckoutRuleScope) {
            // Rule aus falschen Scope aufgerufen?
            $this->logger->log("Die Regel sollte nicht außerhalb des CheckoutRuleScopes ausgeführt werden", true);
            return true;
        }
        */

        if ($this->customer->getCustomFields()[AduConfig::SKIP_CHECK] ?? false) {
            // Admin sagt Kunde soll nicht gescored werden
            return true;
        }
        if($this->api === null){
            $this->logger->log("API Zugriff ist noch nicht freigeschaltet");
            return true;
        }
        return false;
    }
    private function getGoodsAmount(SalesChannelContext $context): float {
        try {
            $cart = $this->cartService->getCart($context->getToken(), $context);
            return $cart->getPrice()->getTotalPrice();
        } catch (\Throwable $e) {
            $this->logger->log($e->getMessage(), true);
            return 0.0;
        }
    }
}
