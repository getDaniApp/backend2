<?php

namespace App\Http\Controllers\API;


use App\Events\OrderChangedEvent;
use App\Http\Controllers\Controller;
use App\Criteria\Users\DriversOfRestaurantCriteria;
use App\Models\Order;
use App\Models\Point;
use App\Notifications\NewOrder;
use App\Notifications\StatusChangedOrder;
use App\Repositories\CartRepository;
use App\Repositories\FoodOrderRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\OrderRepository;
use App\Http\Requests\UpdateOrderRequest;
use App\Repositories\PaymentRepository;
use App\Repositories\UserRepository;
use Braintree\Gateway;
use Flash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use InfyOm\Generator\Criteria\LimitOffsetCriteria;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use Prettus\Validator\Exceptions\ValidatorException;
use Stripe\Stripe;
use Stripe\Token;

/**
 * Class OrderController
 * @package App\Http\Controllers\API
 */
class OrderAPIController extends Controller
{
    /** @var  OrderRepository */
    private $orderRepository;
    /** @var  FoodOrderRepository */
    private $foodOrderRepository;
    /** @var  CartRepository */
    private $cartRepository;
    /** @var  UserRepository */
    private $userRepository;
    /** @var  PaymentRepository */
    private $paymentRepository;
    /** @var  NotificationRepository */
    private $notificationRepository;

    public function __construct(OrderRepository $orderRepo, FoodOrderRepository $foodOrderRepository, CartRepository $cartRepo, PaymentRepository $paymentRepo, NotificationRepository $notificationRepo, UserRepository $userRepository)
    {
        $this->orderRepository = $orderRepo;
        $this->foodOrderRepository = $foodOrderRepository;
        $this->cartRepository = $cartRepo;
        $this->userRepository = $userRepository;
        $this->paymentRepository = $paymentRepo;
        $this->notificationRepository = $notificationRepo;
    }

    /**
     * Display a listing of the Order.
     * GET|HEAD /orders
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        Log::error("get orders");
        try {
            $this->orderRepository->pushCriteria(new RequestCriteria($request));
            $this->orderRepository->pushCriteria(new LimitOffsetCriteria($request));
        } catch (RepositoryException $e) {
            Flash::error($e->getMessage());
        }
        $orders = $this->orderRepository->all();//->whereNotIn('order_status_id', [6])->get();

        return $this->sendResponse($orders->toArray(), 'Orders retrieved successfully');
    }

    /**
     * Display the specified Order.
     * GET|HEAD /orders/{id}
     *
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        /** @var Order $order */
        if (!empty($this->orderRepository)) {
            try {
                $this->orderRepository->pushCriteria(new RequestCriteria($request));
                $this->orderRepository->pushCriteria(new LimitOffsetCriteria($request));
            } catch (RepositoryException $e) {
                Flash::error($e->getMessage());
            }
            $order = $this->orderRepository->findWithoutFail($id);
        }

        if (empty($order)) {
            return $this->sendError('Order not found');
        }

        return $this->sendResponse($order->toArray(), 'Order retrieved successfully');


    }

    /**
     * Store a newly created Order in storage.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $payment = $request->only('payment');
        if (isset($payment['payment']) && $payment['payment']['method']) {
            if ($payment['payment']['method'] == "Credit Card (Stripe Gateway)") {
                return $this->stripPayment($request);
            } else {
                return $this->cashPayment($request);

            }
        }
    }

    private function stripPayment(Request $request)
    {
        $input = $request->all();
        $amount = 0;
        try {
            $user = $this->userRepository->findWithoutFail($input['user_id']);
            if (empty($user)) {
                return $this->sendError('User not found');
            }
            $stripeToken = Token::create(array(
                "card" => array(
                    "number" => $input['stripe_number'],
                    "exp_month" => $input['stripe_exp_month'],
                    "exp_year" => $input['stripe_exp_year'],
                    "cvc" => $input['stripe_cvc'],
                    "name" => $user->name,
                )
            ));
            if ($stripeToken->created > 0) {
                $order = $this->orderRepository->create(
                    $request->only('user_id', 'order_status_id', 'tax', 'delivery_address_id','delivery_fee')
                );
                foreach ($input['foods'] as $foodOrder) {
                    $foodOrder['order_id'] = $order->id;
                    $amount += $foodOrder['price'] * $foodOrder['quantity'];
                    $this->foodOrderRepository->create($foodOrder);
                }
                $amountWithTax = $amount + ($amount * $order->tax / 100);
                $charge = $user->charge((int)($amountWithTax * 100), ['source' => $stripeToken]);
                $payment = $this->paymentRepository->create([
                    "user_id" => $input['user_id'],
                    "description" => trans("lang.payment_order_done"),
                    "price" => $amountWithTax,
                    "status" => $charge->status, // $charge->status
                    "method" => $input['payment']['method'],
                ]);
                $this->orderRepository->update(['payment_id' => $payment->id], $order->id);

                $this->cartRepository->deleteWhere(['user_id' => $order->user_id]);

                Notification::send($order->foodOrders[0]->food->restaurant->users, new NewOrder($order));
            }
        } catch (ValidatorException $e) {
            return $this->sendError($e->getMessage());
        }

        return $this->sendResponse($order->toArray(), __('lang.saved_successfully', ['operator' => __('lang.order')]));
    }

    private function cashPayment(Request $request)
    {
        $input = $request->all();
        $amount = 0;
        $total = 0;
        $total_price = 0;
        $rest_id;
        // $point = new Point;
        try {
            $total =  \DB::table('app_settings')->select('value')->where('key','order_point_save')->get();
        } catch (\Throwable $th) {
            $total = 0;
        }
        try {
            //$input['order_discount'] += $input['order_wallet'];
            $order = $this->orderRepository->create(
                $request->only('user_id', 'order_status_id', 'tax', 'delivery_address_id','delivery_fee','order_discount','hint')
            );

            foreach ($input['foods'] as $foodOrder) {
                $foodOrder['order_id'] = $order->id;
                $amount += $foodOrder['price'] * $foodOrder['quantity'];
                // $total += $foodOrder['point'];
                // $rest_id = $foodOrder['rest_id'];
                $this->foodOrderRepository->create($foodOrder); 
            }
            // $total = $amount + $input['delivery_fee'];
            
            if($input['order_wallet'] > 0){
                $user = $this->userRepository->findWithoutFail($input['user_id']);
                $user->wallet = $user->wallet - $input['order_wallet'];
                $user->save();
                // $amount = $amount - $input['order_wallet'];
            }
            
            $amountWithTax = $amount + ($amount * $order->tax / 100);
            
            // if($input['order_discount'] > 0 && ($amountWithTax - $input['order_discount'])>0)
            //     $amountWithTax = $amountWithTax - $input['order_discount'];
                

            $payment = $this->paymentRepository->create([
                "user_id" => $input['user_id'],
                "description" => trans("lang.payment_order_waiting"),
                "price" => $amountWithTax,
                "status" => $input['payment']['status'],
                "method" => $input['payment']['method'],
            ]);
            
            
            $cashiers = \App\Models\User::whereHas(
                    'roles', function($q){
                        $q->where('name', 'cashier');
                    }
            )->get();
            
            foreach ($cashiers as $cashier){
                Notification::send([$cashier], new NewOrder($order));
            }
            
            
            
            // $point->rest_id = $rest_id;
            // $point->user_id = $input['user_id'];
            // $point->point = $total;
            // $point->actif = true;
            // $point->save();

            $this->orderRepository->update(['payment_id' => $payment->id], $order->id);

            $this->cartRepository->deleteWhere(['user_id' => $order->user_id]);

            Notification::send($order->foodOrders[0]->food->restaurant->users, new NewOrder($order));

        } catch (ValidatorException $e) {
            return $this->sendError($e->getMessage());
        }

        return $this->sendResponse($order->toArray(), __('lang.saved_successfully', ['operator' => __('lang.order')]));
    }

    /**
     * Update the specified Order in storage.
     *
     * @param int $id
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id, Request $request)
    {
        $order = $this->orderRepository->findWithoutFail($id);

        if (empty($order)) {
            return $this->sendError('Order not found');
        }
         $input = $request->all();

        try {
            $order = $this->orderRepository->update($input, $id);
            if ($input['order_status_id'] == 5 && !empty($order)) {
                $this->paymentRepository->update(['status' => 'Paid'], $order['payment_id']);
                // event(new OrderChangedEvent($order));
            }
            Notification::send([$order->user], new StatusChangedOrder($order));

        } catch (ValidatorException $e) {
            return $this->sendError($e->getMessage());
        }

        return $this->sendResponse($order->toArray(), __('lang.saved_successfully', ['operator' => __('lang.order')]));
    }
    
    public function updateOrderStatus(Request $request)
    {
        
        $input = $request->all();

        $order = $this->orderRepository->findWithoutFail($input['id']);

        if (empty($order)) {
            return $this->sendResponse(false, __('lang.saved_successfully', ['operator' => __('lang.order')]));
        }
        try {
            

            if (setting('enable_notifications', false)) {
                if ($input['order_status_id'] != $order->order_status_id) {
                    $order->order_status_id = $input['order_status_id'];
                    $order->save();
                    Notification::send([$order->user], new StatusChangedOrder($order));
                }
            }

            
        } catch (ValidatorException $e) {
            // Flash::error($e->getMessage());
        }

        return $this->sendResponse($order->toArray(), __('lang.saved_successfully', ['operator' => __('lang.order')]));
    }
    
    public function updateOrderPaymentStatus(Request $request)
    {
        
        $input = $request->all();

        $order = $this->orderRepository->findWithoutFail($input['id']);

        // if (empty($order)) {
        //     return $this->sendResponse(false, __('lang.saved_successfully', ['operator' => __('lang.order')]));
        // }
        try {
            
            // $pay = $this->paymentRepository->findWithoutFail($request['payment_id']);

            $this->paymentRepository->update([
                "status" => 'Paid',
            ], $order->payment_id);
            
            
        } catch (ValidatorException $e) {
            // Flash::error($e->getMessage());
        }

        return $this->sendResponse($order->toArray(), __('lang.saved_successfully', ['operator' => __('lang.order')]));
    }
    
    public function updateRestaurantStatus(Request $request)
    {
        
        $input = $request->all();

        // $rest = $this->orderRepository->findWithoutFail($input['id']);
        $rest = \App\Models\Restaurant::where(['id'=>$input['id']])->first();

        if (empty($rest)) {
            return $this->sendResponse(false, __('lang.saved_successfully', ['operator' => __('lang.order')]));
        }
        try {
            

            if ($input['available'] != $rest->available) {
                $rest->available = $input['available'];
                $rest->save();
                // $rest = \App\Models\Restaurant::where(['id'=>$input['id']])->first();
            }

            
        } catch (ValidatorException $e) {
            // Flash::error($e->getMessage());
        }

        return $this->sendResponse($rest->toArray(), __('lang.saved_successfully', ['operator' => __('lang.order')]));
    }
    
    public function restaurantDrivers($id)
    {
        
        try {
            
            // $restaurant = \App\Models\Restaurant::where(['id'=>$id])->first();
            
            $driver = $this->userRepository->getByCriteria(new DriversOfRestaurantCriteria($id));
            
            
        } catch (ValidatorException $e) {
            // Flash::error($e->getMessage());
        }

        return $this->sendResponse($driver->toArray(), __('lang.saved_successfully', ['operator' => __('lang.order')]));
    }
    
    public function updateOrder($id, Request $request)
    {
        $oldOrder = $this->orderRepository->findWithoutFail($id);
        if (empty($oldOrder)) {
            return $this->sendResponse(false, 'Reset link was sent successfully');
        }
        $input = $request->all();
        
        try {
            $order = $this->orderRepository->update($input, $id);

            if (setting('enable_notifications', false)) {
                if (isset($input['order_status_id']) && $input['order_status_id'] != $oldOrder->order_status_id) {
                    Notification::send([$order->user], new StatusChangedOrder($order));
                }

                if (isset($input['driver_id']) && ($input['driver_id'] != $oldOrder['driver_id'])) {
                    $driver = $this->userRepository->findWithoutFail($input['driver_id']);
                    if (!empty($driver)) {
                        Notification::send([$driver], new AssignedOrder($order));
                    }
                }
            }

            $pay = $this->paymentRepository->findWithoutFail($order['payment_id']);

            if(isset($input['status']) ) $this->paymentRepository->update([
                "status" => $input['status'],
            ], $order['payment_id']);

            if(isset($input['status']) && $input['status'] == 'Paid' && $pay->status != 'Paid') {
                // return $statut;
                $user =  $order->user;
                $value =  \DB::table('app_settings')->select('value')->where('key','order_money_save')->get();
                $user->wallet += $value[0]->value;
                $user->save();
                // return $user = $this->userRepository->findWithoutFail($id);
            }

            event(new OrderChangedEvent($order));

            
        } catch (ValidatorException $e) {
            return $this->sendResponse(false, 'Reset link was sent successfully');
        }

       return $this->sendResponse(true, 'Reset link was sent successfully');
    }

}
