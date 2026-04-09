<?php

declare(strict_types=1);

namespace WG\QrBill\Administration\Controller;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use WG\QrBill\Core\Document\DunningDocumentGenerator;

#[Route(defaults: ['_routeScope' => ['api']])]
class DunningController extends AbstractController
{
    public function __construct(
        private readonly DunningDocumentGenerator $dunningDocumentGenerator,
        private readonly EntityRepository $dunningRepository
    ) {
    }

    #[Route(
        path: '/api/wg-dunning/create',
        name: 'api.wg_dunning.create',
        methods: ['POST']
    )]
    public function createDunning(Request $request, Context $context): JsonResponse
    {
        $orderId = $request->get('orderId');

        if (!$orderId) {
            return new JsonResponse(['success' => false, 'message' => 'orderId is required'], 400);
        }

        try {
            $result = $this->dunningDocumentGenerator->createDunning(
                $orderId,
                $context,
                $request->get('salesChannelId')
            );

            return new JsonResponse([
                'success' => true,
                'dunning' => $result,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route(
        path: '/api/wg-dunning/{dunningId}/pdf',
        name: 'api.wg_dunning.pdf',
        methods: ['GET']
    )]
    public function downloadDunningPdf(string $dunningId, Context $context): Response
    {
        try {
            $pdf = $this->dunningDocumentGenerator->generateDunningPdf($dunningId, $context);

            return new Response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="Mahnung-' . substr($dunningId, 0, 8) . '.pdf"',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route(
        path: '/api/wg-dunning/list',
        name: 'api.wg_dunning.list',
        methods: ['GET']
    )]
    public function listDunnings(Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addAssociation('order.orderCustomer');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $dunnings = $this->dunningRepository->search($criteria, $context);

        $data = [];
        foreach ($dunnings->getEntities() as $dunning) {
            $order = $dunning->getOrder();
            $data[] = [
                'id' => $dunning->getId(),
                'orderId' => $dunning->getOrderId(),
                'orderNumber' => $order?->getOrderNumber(),
                'customerName' => $order?->getOrderCustomer()?->getFirstName() . ' ' . $order?->getOrderCustomer()?->getLastName(),
                'level' => $dunning->getLevel(),
                'fee' => $dunning->getFee(),
                'totalAmount' => $dunning->getTotalAmount(),
                'dueDate' => $dunning->getDueDate()?->format('Y-m-d'),
                'sentAt' => $dunning->getSentAt()?->format('Y-m-d H:i'),
                'createdAt' => $dunning->getCreatedAt()?->format('Y-m-d H:i'),
            ];
        }

        return new JsonResponse(['data' => $data, 'total' => $dunnings->getTotal()]);
    }
}
