<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Http;

use App\Identity\Infrastructure\Security\SecurityUser;
use App\Ordering\Application\Command\AddOrderItem;
use App\Ordering\Application\Command\ConfirmOrder;
use App\Ordering\Application\Command\PayOrder;
use App\Ordering\Application\Command\PlaceOrder;
use App\Ordering\Application\Command\RemoveOrderItem;
use App\Ordering\Application\Port\ProductCatalog;
use App\Ordering\Application\Query\OrderTotals;
use App\Ordering\Domain\Model\Order;
use App\Ordering\Domain\Repository\OrderRepository;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\ProductId;
use App\Ordering\Infrastructure\Export\OrderExport;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;

#[Route('/objednavky')]
final class OrderController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly OrderRepository $orders,
        private readonly ProductCatalog $catalog,
    ) {}

    #[Route('', name: 'order_index', methods: ['GET'])]
    public function index(): Response
    {
        $orders = $this->orders->findByCustomer($this->customerId());

        return $this->render('order/index.html.twig', [
            'orders' => $orders,
            'totals' => OrderTotals::of($orders),
        ]);
    }

    #[Route('/export.csv', name: 'order_export', methods: ['GET'])]
    public function export(OrderExport $export): Response
    {
        return new Response(
            $export->toCsv($this->orders->findByCustomer($this->customerId())),
            Response::HTTP_OK,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    #[Route('', name: 'order_place', methods: ['POST'])]
    #[IsCsrfTokenValid('order_place')]
    public function place(): Response
    {
        $orderId = OrderId::generate();
        $this->commandBus->dispatch(new PlaceOrder($orderId, $this->customerId()));

        return $this->redirectToRoute('order_detail', ['id' => $orderId->value]);
    }

    #[Route('/{id}', name: 'order_detail', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function detail(string $id): Response
    {
        $order = $this->ownOrder($id);

        return $this->render('order/detail.html.twig', [
            'order' => $order,
            'products' => $this->catalog->all(),
        ]);
    }

    #[Route('/{id}/polozky', name: 'order_add_item', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('order_edit')]
    public function addItem(string $id, Request $request): Response
    {
        $order = $this->ownOrder($id);
        $product = $this->catalog->find(ProductId::fromString($request->getPayload()->getString('productId')))
            ?? throw $this->createNotFoundException('Product not found.');

        $this->commandBus->dispatch(new AddOrderItem(
            $order->id,
            $product->id,
            max(1, $request->getPayload()->getInt('quantity', 1)),
            $product->price,
        ));

        return $this->redirectToRoute('order_detail', ['id' => $order->id->value]);
    }

    #[Route('/{id}/polozky/{productId}/odebrat', name: 'order_remove_item', requirements: ['id' => Requirement::UUID, 'productId' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('order_edit')]
    public function removeItem(string $id, string $productId): Response
    {
        $order = $this->ownOrder($id);
        $this->commandBus->dispatch(new RemoveOrderItem($order->id, ProductId::fromString($productId)));

        return $this->redirectToRoute('order_detail', ['id' => $order->id->value]);
    }

    #[Route('/{id}/potvrdit', name: 'order_confirm', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('order_edit')]
    public function confirm(string $id): Response
    {
        $order = $this->ownOrder($id);
        $this->commandBus->dispatch(new ConfirmOrder($order->id));

        return $this->redirectToRoute('order_detail', ['id' => $order->id->value]);
    }

    #[Route('/{id}/zaplatit', name: 'order_pay', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsCsrfTokenValid('order_edit')]
    public function pay(string $id): Response
    {
        $order = $this->ownOrder($id);
        $this->commandBus->dispatch(new PayOrder($order->id));

        return $this->redirectToRoute('order_detail', ['id' => $order->id->value]);
    }

    /** Cizí objednávka se tváří jako neexistující (404, ne 403). */
    private function ownOrder(string $id): Order
    {
        $order = $this->orders->find(OrderId::fromString($id));
        if ($order === null || !$order->isOwnedBy($this->customerId())) {
            throw $this->createNotFoundException('Order not found.');
        }

        return $order;
    }

    private function customerId(): CustomerId
    {
        $user = $this->getUser();
        if (!$user instanceof SecurityUser) {
            throw $this->createAccessDeniedException();
        }

        return $user->customerId();
    }
}
