<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Http;

use App\Ordering\Domain\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Přehled objednávek pro správu obchodu (modelová situace z lekce 9.5).
 */
#[Route('/sprava/objednavky')]
#[IsGranted('ROLE_STAFF')]
final class StaffOrderController extends AbstractController
{
    public function __construct(
        private readonly OrderRepository $orders,
    ) {}

    #[Route('/stornovane', name: 'staff_cancelled_orders', methods: ['GET'])]
    public function cancelled(Request $request): Response
    {
        return $this->render('order/cancelled.html.twig', [
            'orders' => $this->orders->findByStatus($request->query->getString('stav', 'cancelled')),
        ]);
    }
}
