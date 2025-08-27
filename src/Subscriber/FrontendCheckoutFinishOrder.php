<?php declare(strict_types=1);

namespace Adu\CheckAndCollect\Subscriber;

use Adu\CheckAndCollect\Model\ServiceLocator;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
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

        // Bypass für Bug im Shopware Rulebuilder - Konstruktor defekt und es wird nicht gefixed werden.
        $serviceLocator = ServiceLocator::getInstance();
        $serviceLocator->set('ccSoapApi', $this->soapService);
        $serviceLocator->set('ccSession', $this->session);
        $serviceLocator->set('ccLogger', $this->log);
        $serviceLocator->set('cartService', $this->cartService);
        $serviceLocator->set('container', $this->container);
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
        if ('frontend.checkout.finish.order' != $route) {
            if($route === "frontend.account.login.imitate-customer"){
                $this->session->remove('adu_score_value');
            }
            return;
        }


        $context = $event->getArguments();

        // Gggfls variable
        /** @var SalesChannelContext[] $salesChannelContexts */
        $salesChannelContexts = array_filter($context, fn($c) => $c instanceof SalesChannelContext);
        $salesChannelContext = array_pop($salesChannelContexts);
        if(!$salesChannelContext){
            $this->logger('SalesChannelContext konnte nicht geladen werden', true);
            return;
        }
        $this->soapService->setScope($salesChannelContext->getSalesChannel()->getId());


        $payment = $salesChannelContext->getPaymentMethod();
        $pname = $payment->getName();
        $this->logger($pname . ' - Starte Regelermittlung');
        $availibilityRule = $payment->getAvailabilityRuleId();

        // ScoreRule im RuleBuilder?
        if ($availibilityRule === null) {
            $this->logger($pname . ' - Keine Verfügbarkeitsregel gefunden.');
            return;
        }
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
        $context = new Context(new SystemSource());
        $entities = $ruleRepository->search($criteria, $context);

        if ($entities->getTotal() == 0) {
            $this->logger($pname . ' - Keine Payload zur Verfügbarkeitsregel gefunden.');
            return;
        }
        $this->logger($pname . ' - Regel wurde ermittelt.');

        if ($this->shouldCheck()) {
            $this->session->set('ccCheck', true);
        }
    }
    private function shouldCheck(): bool{
        if(!$this->soapService->activeApi){
            // Einstellung haben die Prüfung deaktiviert
            return false;
        }
        if(!empty($this->soapService->ipAddress)){
            // Prüfung wurde auf eine spezifische IP eingeschränkt
            return $_SERVER['REMOTE_ADDR'] === $this->soapService->ipAddress;
        }
        return true;
    }

    private function logger(string|\Stringable $msg, bool $crit = false)
    {
        if (!$this->soapService?->activeLog) {
            return;
        }
        $crit ?
            $this->log->critical($msg) :
            $this->log->debug($msg);
    }
}
