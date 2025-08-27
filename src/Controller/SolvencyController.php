<?php declare(strict_types=1);

namespace Adu\CheckAndCollect\Controller;

use PHPUnit\Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
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

        // Ausführen wenn zuordbar
        if (isset($id)) {
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
        } else {
            return new JsonResponse([
                'Keine Daten - ' . $id
            ]);
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
            $soap = $this->container->get('Adu\CheckAndCollect\Service\SoapService');

            $result = $soap->getToken();

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
            $soap = $this->container->get('Adu\CheckAndCollect\Service\SoapService');

            $result = $soap->getCredits();

            return new JsonResponse($result);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()]);
        }
    }
}


