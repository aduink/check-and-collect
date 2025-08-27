<?php declare(strict_types=1);

namespace Adu\CheckAndCollect\Controller;

use Adu\CheckAndCollect\Service\SoapService;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Composer\InstalledVersions;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Adu\CheckAndCollect\Model\SolvencyDebtorHandler;

/**
 * @internal
 * @Route(defaults={"_routeScope"={"api"}})
 */
class SolvencyController extends AbstractController
{
    /**
     * @Route("/api/_action/adu/version-request", name="api.action.adu.version-request", methods={"GET"})
     */
    public function getVersions(): JsonResponse
    {
        try{
            return new JsonResponse([
                'shop' => InstalledVersions::getVersion("shopware/core"),
                'cc' => CHECKANDCOLLECTVERSION
            ]);
        }catch (\Exception $e){
            return new JsonResponse(['error' => $e->getMessage()]);
        }
    }

    /**
     * @Route("/api/_action/adu/solvency-api-request", name="api.action.adu.solvency-api-request", methods={"GET", "POST"})
     * @param Request $request
     * @param Context $context
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function solvencyApiRequest(Request $request, Context $context): JsonResponse
    {
        error_reporting(0);
        $id = $request->request->get('id');

        // Ausführen, wenn zuordbar
        if (!isset($id)) {
            return new JsonResponse(['error' => 'Keine CustomerId übergeben']);
        }
        try {
            // Debitoren handling
            $debtor = new SolvencyDebtorHandler($this->container, Context::createDefaultContext());
            $debtorData = $debtor->formDebtorData($id);

            // Soap Service laden
            $soap = $this->container->get('Adu\CheckAndCollect\Service\SoapService');

            $result = $soap->getSolvencyCheck($debtorData, false);

            return new JsonResponse([$result]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()]);
        }
    }

    /**
     * @Route("/api/_action/adu/get-token", name="api.action.adu.get-token", methods={"GET", "POST"})
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getToken(): JsonResponse
    {
        try {
            // Soap Service laden
            /** @var SoapService $soap */
            $soap = $this->container->get('Adu\CheckAndCollect\Service\SoapService');

            $result = $soap->getToken();
            if(!isset($result['token'])){
                throw new \Exception("SoapsService hat keinen Token zurückgegeben");
            }

            return new JsonResponse($result);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()]);
        }
    }

    /**
     * @Route("/api/_action/adu/get-credits", name="api.action.adu.get-credits", methods={"GET", "POST"})
     * @return JsonResponse
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getCredits(): JsonResponse
    {
        try {
            // Soap Service laden
            /** @var SoapService $soap */
            $soap = $this->container->get('Adu\CheckAndCollect\Service\SoapService');

            $result = $soap->getCredits();

            return new JsonResponse($result);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()]);
        }
    }
}


