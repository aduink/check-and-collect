<?php declare(strict_types=1);

namespace Adu\CheckAndCollect\Controller;

use Adu\CheckAndCollect\Model\RatingRequest;
use Adu\CheckAndCollect\Model\Scoring;
use Adu\CheckAndCollect\Service\ApiService;
use Adu\CheckAndCollect\Service\Logger;
use Composer\InstalledVersions;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Service\Attribute\Required;

#[\Symfony\Component\Routing\Annotation\Route(defaults: ["_routeScope" => ["api"]])]
class SolvencyController extends AbstractController
{
    private Logger $logger;
    private ApiService $soap;

    #[Required]
    public function setDependencies(Logger $logger, ApiService $soap): void
    {
        $this->logger = $logger;
        $this->soap = $soap;
    }

    #[Route("/api/_action/adu/version-request", name: "api.action.adu.version-request", methods: ["GET"])]
    public function getVersions(Request $request, EntityRepository $customerRepository): JsonResponse
    {
        return $this->respondWith(fn() => [
            'shop' => InstalledVersions::getVersion("shopware/core"),
            'cc' => CHECKANDCOLLECTVERSION,
        ]);
    }

    #[Route("/api/_action/adu/solvency-api-request", name: "api.action.adu.solvency-api-request", methods: ["GET", "POST"])]
    public function solvencyApiRequest(Request $request, EntityRepository $customerRepository): JsonResponse
    {
        $id = $request->request->get('id');

        // Ausführen, wenn zuordbar
        return $this->respondWith(fn() => $this->solvencyFromCustomerId($id, $customerRepository));
    }

    /**
     * @throws \Exception
     */
    private function solvencyFromCustomerId(?string $id, EntityRepository $customerRepository): Scoring
    {
        if (!$id) {
            throw new \Exception("Keine ID übergeben");
        }
        $customer = $this->getCustomer($customerRepository, $id);
        if (!$customer) {
            throw new \Exception("Customer existiert nicht");
        }
        $request = RatingRequest::fromCustomer($customer);
        $request->setCache(false);
        return $this->soap->getSolvencyCheck($request);
    }

    #[Route("/api/_action/adu/get-token", name: "api.action.adu.get-token", methods: ["GET", "POST"])]
    public function getToken(): JsonResponse
    {
        return $this->respondWith(fn() => $this->soap->getToken());
    }

    #[Route("/api/_action/adu/get-credits", name: "api.action.adu.get-credits", methods: ["GET", "POST"])]
    public function getCredits(): JsonResponse
    {
        return $this->respondWith(fn() => $this->soap->getCredits());
    }

    private function respondWith(\Closure $closure): JsonResponse
    {
        try {
            $resp = $this->json($closure());
            $this->logger->log("Rückgabe aus dem SolvencyController", context: [$resp->getContent()]);
            return $resp;

        } catch (\Throwable $e) {
            $this->logger->log("Fehlerhafte anfrage", true, [$e]);
            return $this->json(['errors' => [$e->getMessage()]], 500);
        }
    }

    public function getCustomer(EntityRepository $repo, string $id): ?CustomerEntity
    {
        $customerCriteria = (new Criteria([$id]))
            ->addAssociation('addresses')
            ->addAssociation('addresses.country');

        $context = new Context(new SystemSource());
        $result = $repo
            ->search($customerCriteria, $context)
            ->getEntities();

        return $result->first();
    }
}