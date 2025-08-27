<?php declare(strict_types=1);

namespace Adu\CheckAndCollect\Subscriber;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Adu\CheckAndCollect\Service\SoapService;


/**
 * Class FrontendCheckoutFinishOrder
 * @package Adu\CaC\Subscriber
 */
class FrontendCheckoutFinishOrder implements EventSubscriberInterface
{
    private $session;
    protected LoggerInterface $log;
    protected CartService $cartService;
    protected ContainerInterface $container;
    protected SoapService $soapService;

    /**
     * @param SoapService $soapService
     * @param ContainerInterface $container
     * @param CartService $cartService
     * @param LoggerInterface $logger
     * @param RequestStack $request
     */
    public function __construct(
        SoapService        $soapService,
        ContainerInterface $container,
        CartService        $cartService,
        LoggerInterface    $log,
        RequestStack       $requestStack)
    {
        $this->soapService = $soapService;
        $this->container = $container;
        $this->cartService = $cartService;
        $this->log = $log;
        $this->session = $requestStack->getSession();
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ControllerArgumentsEvent::class => 'onFrontendCheckoutFinishOrder'
        ];
    }

    /**
     * @param ControllerArgumentsEvent $event
     */
    public function onFrontendCheckoutFinishOrder(ControllerArgumentsEvent $event)
    {
        $route = $event->getRequest()->attributes->get('_route');

        if ('frontend.checkout.finish.order' == $route) {

            $context = $event->getArguments();
            $salesChannelContext = '';

            // Gggfls variable
            foreach ($context as $ko => $vo) {
                if (is_a($vo, "Shopware\Core\System\SalesChannel\SalesChannelContext")) {
                    $salesChannelContext = $vo;
                }
            }

            if (is_a($salesChannelContext, "Shopware\Core\System\SalesChannel\SalesChannelContext")) {
                $this->soapService->setScope($salesChannelContext->getSalesChannel()->getId());
                if ($this->soapService->activeApi && (empty($this->soapService->ipAddress) || $_SERVER['REMOTE_ADDR'] == $this->soapService->ipAddress)) {

                    $payment = $salesChannelContext->getPaymentMethod();
                    $pname = $payment->getName();
                    $this->logger($pname . ' - Starte Regelermittlung');
                    $availibilityRule = $payment->getAvailabilityRuleId();

                    if ($this->session->get('route') !== true && null !== $salesChannelContext->getCustomer()) {
                        // ScoreRule im RuleBuilder?
                        if (null !== $availibilityRule) {

                            $ruleRepository = $this->container->get('rule.repository');

                            $criteria = new Criteria();
                            $criteria->addFilter(
                                new MultiFilter(
                                    MultiFilter::CONNECTION_OR,
                                    [
                                        new ContainsFilter('payload', 'ScoreRule'),
                                        new ContainsFilter('payload', 'CustomerRule')
                                    ]
                                )
                            );
                            $criteria->addFilter(
                                new EqualsFilter('id', $availibilityRule)
                            );

                            $entities = $ruleRepository->search($criteria, \Shopware\Core\Framework\Context::createDefaultContext());

                            if ($entities->getTotal() == 0) {
                                $this->logger($pname . ' - Keine Payload zur Verfügbarkeitsregel gefunden.');
                                return;
                            }
                            $this->logger($pname . ' - Regel wurde ermittelt.');
                        } else {
                            $this->logger($pname . ' - Keine Verfügbarkeitsregel gefunden.');
                            return;
                        }

                        $this->logger($pname . ' - Context wird geladen.');
                        $customer = $salesChannelContext->getCustomer();

                        $this->logger($pname . ' - Warenkorb wird geladen.');
                        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);
                        $goodsAmount = $cart->getPrice()->getTotalPrice();

                        // Scorewertermittlung
                        $this->logger($pname . ' - Score wird ermittelt.');
                        $result = $this->getScore($customer, $goodsAmount);

                        // Customerfields update
                        $customArr = $customer->getCustomFields();
                        $customArr['adu_score_value'] = floatval($result['score']);
                        $customArr['adu_additional_value'] = (string)$result['additionalInfo'];

                        $this->logger($pname . ' - Customfields werden aktualisiert.');
                        $customerRepository = $this->container->get('customer.repository');
                        $customerRepository->update(
                            [
                                ['id' => $customer->getid(), 'customFields' => $customArr],
                            ],
                            \Shopware\Core\Framework\Context::createDefaultContext()
                        );

                        // Frontend Cache
                        $this->session->set('route', true);
                    } else {
                        if (null !== $this->session->get('route')) {
                            $this->logger('Verarbeitung wurde durchgeführt. Shopware Rule Builder wird ausgeführt.');
                        } else {
                            $this->logger('Verbeitung wurde nicht gestartet. Es sind (noch) keine Kundendaten vorhanden.');
                        }

                        return;
                    }
                }
            }
        } else {
            $this->logger('SalesChannelContext konnte nicht geladen werden');
        }
    }

    /**
     * @param $customer
     * @param $goodsAmount
     * @return mixed
     * @throws \Exception
     */
    private
    function getScore($customer, $goodsAmount)
    {
        $shopsetting = ['salution' => $customer->getSalutation()->getLetterName(), 'amount' => $goodsAmount, 'customerEntityId' => $customer->getid()];

        $addressArr = [];
        $addressArr['firstname'] = (NULL != $customer->getFirstName()) ? $customer->getFirstName() : '';
        $addressArr['lastname'] = (NULL != $customer->getLastName()) ? $customer->getLastName() : '';
        $addressArr['street'] = (NULL != $customer->getActiveBillingAddress()->getStreet()) ? $customer->getActiveBillingAddress()->getStreet() : '';
        $addressArr['housenumber'] = '';
        $addressArr['zipcode'] = (NULL != $customer->getActiveBillingAddress()->getZipcode()) ? $customer->getActiveBillingAddress()->getZipcode() : '';
        $addressArr['city'] = (NULL != $customer->getActiveBillingAddress()->getCity()) ? $customer->getActiveBillingAddress()->getCity() : '';
        $addressArr['company'] = (NULL != $customer->getActiveBillingAddress()->getCompany()) ? $customer->getActiveBillingAddress()->getCompany() : '';
        $addressArr['phone'] = (NULL != $customer->getActiveBillingAddress()->getPhoneNumber()) ? $customer->getActiveBillingAddress()->getPhoneNumber() : '';
        $addressArr['email'] = (NULL != $customer->getEmail()) ? $customer->getEmail() : '';
        $addressArr['birthday'] = (NULL != $customer->getBirthday()) ? $customer->getBirthday() : '';
        $addressArr['ordernumber'] = $customer->getId();
        $addressArr['country'] = $customer->getActiveBillingAddress()->getCountry()->getIso();
        $addressArr['shopsetting'] = $shopsetting;
        $addressArr['customerId'] = $customer->getCustomerNumber();

        // Nur prüfen wenn eine Änderung zur vorherigen Eingabe existiert
        $hashStr = $addressArr['firstname'] . $addressArr['lastname'] . $addressArr['street'] . $addressArr['housenumber'] . $addressArr['zipcode'] . $addressArr['city'] . $addressArr['company'];
        $hashStr = hash('sha256', $hashStr);

        // Vorhanden in der aktuellen Sitzung?
        if (!empty($this->session->get('score')) && !empty($this->session->get('checksum')) && $hashStr == $this->session->get('checksum')) return $this->session->get('score');

        // Neuer Hash, neue Prüfung
        $this->session->set('checksum', $hashStr);

        // Prüfen
        $result = $this->soapService->getSolvencyCheck($addressArr);
        $this->session->set('score', $result);

        return $result;
    }

    /**
     * @param $msg
     * @param bool $crit
     */
    private
    function logger($msg, $crit = false)
    {
        if ($this->soapService->activeLog == true) {
            if ($crit) {
                $this->log->critical($msg);
            } else {
                $this->log->debug($msg);
            }
        }
    }
}
