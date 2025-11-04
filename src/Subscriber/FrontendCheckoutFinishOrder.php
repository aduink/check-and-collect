<?php declare(strict_types=1);

namespace Adu\CheckAndCollect\Subscriber;

use Adu\CheckAndCollect\Model\ServiceLocator;
use Adu\CheckAndCollect\Service\AduConfig;
use Adu\CheckAndCollect\Service\ConfiguredService;
use Adu\CheckAndCollect\Service\AduLogger;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedCriteriaEvent;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Content\Rule\RuleEvents;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextCreatedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Adu\CheckAndCollect\Service\ApiService;
use Symfony\Contracts\Service\Attribute\Required;


/**
 * Class FrontendCheckoutFinishOrder
 * @package Adu\CaC\Subscriber
 */
class FrontendCheckoutFinishOrder
{
    use ConfiguredService;

    private ?SessionInterface $session;
    private ServiceLocator $locator;

    // Die eine Route für Standard Storefront die andere für Headless api
    private const CHECKOUT_ROUTES = ["store-api.checkout.cart.order", "frontend.checkout.finish.order", "frontend.account.edit-order.update-order"];


    public function __construct(
        protected ApiService       $apiService,
        protected EntityRepository $ruleRepository,
        protected EntityRepository $customerRepository,
        protected EntityRepository $paymentMethodRepository,
        protected CartService      $cartService,
        protected AduLogger        $logger,
        RequestStack               $requestStack
    )
    {
        try{
            $this->session = $requestStack->getSession();
        }catch (SessionNotFoundException){
            $this->session = null;
        }
    }

    #[Required]
    public function setConfig(AduConfig $config): void
    {
        $this->config = $config;
        $this->locator = new ServiceLocator(
            null, // API noch nicht freigeben
            $this->session,
            $this->logger,
            $this->customerRepository,
            $this->config,
            $this->cartService
        );
    }

    /**
     * Dient nur als stub um den Servicelocator zu füllen
     * Benötigt für Den aufruf der Zahlungsmehtoden aus Headless Stores (/store-api/payment-method)
     */
    #[AsEventListener(event: RuleEvents::RULE_LOADED_EVENT)]
    public function ruleLoadedListener(
        //EntityLoadedEvent $event
    ): void {
        $this->logger->debug("Rule loaded!");
    }

    #[AsEventListener(event: SalesChannelContextCreatedEvent::class)]
    public function onSalesChannelContextCreated(SalesChannelContextCreatedEvent $event): void
    {
        // Just to initialize the service before the sales channel rules are evaluated
    }
    #[AsEventListener(CheckoutOrderPlacedCriteriaEvent::class)]
    public function orderPlacedListener(): void
    {
        $this->logger->debug("CheckoutOrderPlacedCriteria");
    }
    #[AsEventListener(CheckoutOrderPlacedEvent::class)]
    public function orderPlacedListenerEvent(): void
    {
        $this->logger->debug("CheckoutOrderPlaced");
    }

    #[AsEventListener(event: ControllerArgumentsEvent::class)]
    public function onFrontendCheckoutFinishOrder(ControllerArgumentsEvent $event): void
    {
        $route = $event->getRequest()->attributes->get('_route');
        if($this->logger->isActive() && !in_array($route, ['api.notification.message', 'api.action.message-queue.consume', 'api.info.queue', 'api.action.scheduled-task.run'])){
            $this->logger->debug("Route $route");
        }

        // TODO: Wenn route order update und input mit paymentMethodId existent
        if (!in_array($route, self::CHECKOUT_ROUTES) || !$this->shouldCheck()) {
            // Manche Shopbetreiber loggen sich für mehrere Kunden hintereinander ein und bestellen für sie.
            // So kann für jeden eingeloggten Kunden ein neuer Score gezogen werden
            if ($route === "frontend.account.login.imitate-customer") {
                $v = $this->session->remove('adu_score_value');
                $this->logger->debug("Neuer Kunde wird imitiert. Adu_score_value wird aus Session gelöscht", context: $v ?? []);
            }
            return;
        }
        if($route === 'frontend.account.edit-order.update-order' && !$event->getRequest()->get('paymentMethodId')){
            // Order wurde geupdated aber Zahlungsmethode wurde nicht verändertr
            $this->logger->debug("Bestellung wurde verädnert aber die Zahlungsmethode hat sich nicht verändert");
        }
        try {
            $this->validateEvent($event);
        } catch (\Exception $e) {
            $this->logger->critical('Prüfung des Event ist Fehlgeschlagen', [$e]);
        }
    }

    /**
     * @throws \Exception
     */
    private function validateEvent(ControllerArgumentsEvent $event): void
    {
        $salesChannelContext = $this->getSalesChannelContext($event);
        $payment = $salesChannelContext->getPaymentMethod();
        if($this->paymentMethodHasAduRule($payment)){
            $this->kickstartCreditCheck($salesChannelContext);
        }else{
            $this->logger->debug("Zahlungsmethode: ". $payment->getName(). " soll nicht geprüft werden");
        }
    }

    /**
     * Prüft, ob die übergebene Zahlungsmethode eine AvailabillityRule hat, und ob diese Eine kostenpflichtige Scoreabfrage benötigt
     */
    private function paymentMethodHasAduRule(PaymentMethodEntity $payment): bool {
        $this->logger->debug('Prüfung der Zahlungsmethode ' . $payment->getName());
        $availabilityRuleId = $payment->getAvailabilityRuleId();
        if (!$availabilityRuleId) {
            $this->logger->debug("Der Zahlungsmethode sind keine Verfügbarkeitsregeln zugeordnet");
            return false;
        }
        $rules = $this->getRules($availabilityRuleId);
        if (!$rules->getTotal()) {
            $this->logger->debug("Der Zahlungsmethode sind keine Bonitätsregeln zugewiesen");
            return false;
        }
        return true;
    }

    private function kickstartCreditCheck(SalesChannelContext $salesChannelContext): void
    {
        $this->logger->info("API-Zugriff wird freigeschaltet");

        // Sobald die Regeln Zugriff auf den ApiService haben, haben Sie die Möglichkeit über die API auf einen neuen Score zuzugreifen
        $this->apiService->setScope($salesChannelContext->getSalesChannel()->getId());
        $this->locator->ccApi = $this->apiService;
    }

    /**
     * @throws \Exception
     */
    private function getSalesChannelContext(ControllerArgumentsEvent $event): SalesChannelContext
    {
        $context = $event->getArguments();

        // Gggfls variable
        /** @var SalesChannelContext[] $salesChannelContexts */
        $salesChannelContexts = array_filter($context, fn($c) => $c instanceof SalesChannelContext);
        $salesChannelContext = array_pop($salesChannelContexts);
        if (!$salesChannelContext) {
            throw new \Exception('SalesChannelContext konnte nicht geladen werden');
        }
        return $salesChannelContext;
    }

    /**
     * @param string $availabilityRuleId
     * @return EntitySearchResult<RuleEntity>
     * Prüfen ob die AvailabillityRule AduRegeln nutzt
     */
    private function getRules(string $availabilityRuleId): EntitySearchResult
    {
        $criteria = new Criteria([$availabilityRuleId]);
        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_OR, [
                new ContainsFilter('payload', 'ScoreRule'),
                new ContainsFilter('payload', 'CustomerRule'),
                new ContainsFilter('payload', 'AwarenessRule'),
            ])
        );
        $context = new Context(new SystemSource());
        return $this->ruleRepository->search($criteria, $context);
    }

    private function shouldCheck(): bool
    {
        if (!$this->config->activeApi()) {
            // Einstellung haben die Prüfung deaktiviert
            $this->logger->debug("Prüfung ist durch die Plugin-Einstellung deaktiviert");
            return false;
        }
        $ip = $this->config->ipAddress();
        if ($ip) {
            $this->logger->debug("Die Prüfung ist auf eine Spezifische IP beschränkt. Match? " . ($_SERVER['REMOTE_ADDR'] === $ip ? "Ja" : "Nein"));
            // Prüfung wurde auf eine spezifische IP eingeschränkt
            return $_SERVER['REMOTE_ADDR'] === $ip;
        }
        return true;
    }
}
