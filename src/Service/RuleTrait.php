<?php
declare(strict_types=1);

namespace Adu\CheckAndCollect\Service;

use Adu\CheckAndCollect\Model\ServiceLocator;
use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Stringable;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

trait RuleTrait
{
    private ?SoapService $soapService;
    private ?LoggerInterface $log;
    private ?SessionInterface $session;
    private ?CartService $cartService;
    private ?ContainerInterface $container;
    protected string $operator;

    private function loadLocator(): void
    {
        $serviceLocator = ServiceLocator::getInstance();
        $this->session = $serviceLocator->get('ccSession');
        $this->log = $serviceLocator->get('ccLogger');
        $this->soapService = $serviceLocator->get('ccSoapApi');
        $this->cartService = $serviceLocator->get('cartService');
        $this->container = $serviceLocator->get('container');
    }

    /**
     * @param CustomerEntity $customer
     * @param $goodsAmount
     * @return array{score:float,additionalInfo:string,company:bool,error:bool}
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    private function getScore(CustomerEntity $customer, $goodsAmount): array
    {
        $shopsetting = ['salution' => $customer->getSalutation()->getLetterName(), 'amount' => $goodsAmount, 'customerEntityId' => $customer->getid()];
        $a = $customer->getActiveBillingAddress();

        $addressArr = [
            'firstname' => $customer->getFirstName() ?? '',
            'lastname' => $customer->getLastName() ?? '',
            'street' => $a?->getStreet() ?? '',
            'housenumber' => '',
            'zipcode' => $a?->getZipcode() ?? '',
            'city' => $a?->getCity() ?? '',
            'company' => $a?->getCompany() ?? '',
            'phone' => $a?->getPhoneNumber() ?? '',
            'email' => $customer->getEmail() ?? '',
            'birthday' => $customer->getBirthday(),
            'ordernumber' => $customer->getId(),
            'country' => $a?->getCountry()?->getIso(),
            'shopsetting' => $shopsetting,
            'customerId' => $customer->getCustomerNumber(),
        ];

        $hashStr = $addressArr['firstname'] . $addressArr['lastname'] . $addressArr['street'] . $addressArr['housenumber'] . $addressArr['zipcode'] . $addressArr['city'] . $addressArr['company'];
        $hashStr = hash('sha256', $hashStr);
        $g = fn(string $name) => $this->session->get($name) ?? $_SESSION[$name] ?? null;

        // Vorhanden in der aktuellen Sitzung?
        if (!empty($g('adu_score_value')) && $hashStr == $g('checksum')) {
            //$this->log("Returning score from Session");
            return $g('adu_score_value');
        }

        // Neuer Hash, neue Prüfung
        $this->session->set('checksum', $hashStr);

        // Prüfen
        $result = $this->soapService->getSolvencyCheck($addressArr);

        // Bisschen verwirrend das hier 'adu_score_value' das ganze array ist und im customarr nur der score selber
        $this->session->set('adu_score_value', $result);

        // Customerfields update
        $customArr = $customer->getCustomFields();
        $customArr['adu_score_value'] = $result['score'];
        $customArr['adu_additional_value'] = (string)$result['additionalInfo'];

        $this->logger('Customfields werden aktualisiert.', context: $result);
        /** @var EntityRepository<CustomerEntity> $customerRepository */
        $customerRepository = $this->container->get('customer.repository');
        $defaultContext = new Context(new SystemSource());
        $customerRepository->update(
            [
                ['id' => $customer->getid(), 'customFields' => $customArr],
            ],
            $defaultContext
        );

        return $result;
    }

    /**
     * Prüfung ob die Technischen gegebenheiten für ein Ziehen eines neuen Scores gegeben sind
     * @param RuleScope $scope
     * @return bool
     */
    private function cantCheck(RuleScope $scope): bool {
        if(!$scope instanceof CheckoutRuleScope){
            // Rule aus falschen Scope aufgerufen ??
            //$this->log("Not the correct scope");
            return true;
        }
        if($this->soapService === null){
            return true;
        }
        $context = $scope->getSalesChannelContext();

        // Vollständige Daten werden benötigt.
        $customer = $context->getCustomer();
        if(!$customer){
            //$this->log("No Customer");
            // Customer kann nicht gefunden werden
            return true;
        }
        if (!($this->session->get('ccCheck') ?? false)) {
            //$this->log("Temporarily disabled");
            // Session sagt es soll nicht mehr gescored werden
            return true;
        }
        return false;
    }
    /**
     * Prüfen ob Der check komplett übersprungen werden soll und true zurückgegebn werden soltle
     */
    private function shoudlSkipCheck(RuleScope $scope): bool
    {
        return (bool)($scope->getSalesChannelContext()->getCustomer()?->getCustomFields()['deny_solvencycheck_user'] ?? false);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    private function getNewScore(SalesChannelContext $context): array
    {
        $customer = $context->getCustomer();
        if($customer === null){
            throw new Exception("Kunde konnte nicht ermittelt werden");
        }
        // Wenn man nicht hier temporär auf false setzt kommt man in einen infinit loop
        $this->session->set('ccCheck', false);
        $cart = $this->cartService->getCart($context->getToken(), $context);
        $goodsAmount = $cart->getPrice()->getTotalPrice();
        $this->session->set('ccCheck', true);
        $score = $this->getScore($customer, $goodsAmount);
        $this->session->set('ccCheck', false);
        return $score;
    }

    private function logger(string|Stringable $msg, bool $crit = false, array $context = []): void
    {
        if (!$this->soapService?->activeLog) {
            return;
        }
        $crit ?
            $this->log->critical($msg, $context) :
            $this->log->debug($msg, $context);
    }
}
