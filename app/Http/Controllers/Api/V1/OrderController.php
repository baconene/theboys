<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Repositories\OrderRepository;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private OrderRepository $orderRepository
    ) {}

    public function index(): JsonResponse
    {
        $this->checkPermission('view orders');

        $orders = Order::with(['items.product', 'user', 'queueNumber'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(OrderResource::collection($orders));
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $this->checkPermission('create orders');

        $order = $this->orderService->createOrder($request->validated());

        return response()->json(new OrderResource($order), 201);
    }

    public function show(Order $order): JsonResponse
    {
        $this->checkPermission('view orders');

        $order = $this->orderRepository->getWithItems($order->id);

        return response()->json(new OrderResource($order));
    }

    public function update(Order $order, Request $request): JsonResponse
    {
        $this->checkPermission('update orders');

        $data = $request->validate([
            'notes'                  => 'nullable|string|max:500',
            'discount_amount'        => 'nullable|numeric|min:0',
            'items'                  => 'required|array|min:1',
            'items.*.product_id'     => 'required|integer|exists:products,id',
            'items.*.quantity'       => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($order, $data) {
            if (array_key_exists('notes', $data)) {
                $order->update(['notes' => $data['notes']]);
            }
            if (array_key_exists('discount_amount', $data)) {
                $order->update(['discount_amount' => $data['discount_amount']]);
            }

            $order->items()->delete();

            foreach ($data['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $product->id,
                    'quantity'   => $itemData['quantity'],
                    'unit_price' => $product->price,
                    'subtotal'   => $product->price * $itemData['quantity'],
                ]);
            }

            $order->calculateTotals();
        });

        $fresh = $order->fresh(['items.product', 'queueNumber']);
        return response()->json(\App\Http\Controllers\QueueMonitorController::formatOrder($fresh));
    }

    public function destroy(Order $order): Response
    {
        $this->checkPermission('delete orders');

        return response()->noContent();
    }

    public function updateStatus(Order $order, UpdateOrderStatusRequest $request): JsonResponse
    {
        $this->checkPermission('update orders');

        $order = $this->orderService->updateOrderStatus($order, $request->enum('status', OrderStatus::class));

        return response()->json(new OrderResource($order));
    }

    public function cancel(Order $order): JsonResponse
    {
        $this->checkPermission('update orders');

        $order = $this->orderService->cancelOrder($order, request()->input('reason'));

        return response()->json(new OrderResource($order));
    }

    public function activeOrders(): JsonResponse
    {
        $this->checkPermission('view orders');

        $orders = $this->orderRepository->getActiveOrders();

        return response()->json(
            $orders->map(fn ($o) => \App\Http\Controllers\QueueMonitorController::formatOrder($o))->values()
        );
    }

    public function queueOrders(): JsonResponse
    {
        $this->checkPermission('view orders');

        $orders = $this->orderRepository->getQueueOrders();

        return response()->json(OrderResource::collection($orders));
    }

    private function checkPermission(string $permission): void
    {
        if (! auth()->user()?->hasAnyRole('admin') && ! auth()->user()?->hasPermissionTo($permission)) {
            abort(403, 'Unauthorized');
        }
    }
}
