<?php

namespace App\Http\Controllers\API\cashier;

use App\Events\UserRoleChangedEvent;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use App\Models\FoodOrder;
use App\Models\OrderStatus;
use App\Repositories\CustomFieldRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UploadRepository;
use App\Repositories\UserRepository;
use App\Notifications\AssignedOrder;
use App\Http\Requests\UpdateOrderRequest;
use App\Events\OrderChangedEvent;
use App\Notifications\StatusChangedOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Response;
use Prettus\Validator\Exceptions\ValidatorException;
use App\Repositories\OrderRepository;
use App\Repositories\OrderStatusRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\PaymentRepository;

class UserAPIController extends Controller
{
    private $userRepository;
    private $uploadRepository;
    private $roleRepository;
    private $customFieldRepository;
        /** @var  OrderRepository */
    private $orderRepository;
    
    private $orderStatusRepository;
    /** @var  NotificationRepository */
    private $notificationRepository;
    /** @var  PaymentRepository */
    private $paymentRepository;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(OrderRepository $orderRepo,UserRepository $userRepository, UploadRepository $uploadRepository, RoleRepository $roleRepository, CustomFieldRepository $customFieldRepo
                                , OrderStatusRepository $orderStatusRepo, NotificationRepository $notificationRepo, PaymentRepository $paymentRepo)
    {
        $this->orderRepository = $orderRepo;
        $this->userRepository = $userRepository;
        $this->uploadRepository = $uploadRepository;
        $this->roleRepository = $roleRepository;
        $this->customFieldRepository = $customFieldRepo;
        $this->orderStatusRepository = $orderStatusRepo;
        $this->notificationRepository = $notificationRepo;
        $this->paymentRepository = $paymentRepo;
    }

    function login(Request $request)
    {
        if (auth()->attempt(['email' => $request->input('email'), 'password' => $request->input('password')])) {
            // Authentication passed...
            $user = auth()->user();
            if (!$user->hasRole('cashier')){
                return $this->sendResponse([
                    'error' => 'Unauthorised user',
                    'code' => 401,
                ], 'User not cashier');
            }
            $user->device_token = $request->input('device_token','');
            $user->save();
            return $this->sendResponse($user, 'User retrieved successfully');
        }

        return $this->sendResponse([
            'error' => 'Unauthenticated user',
            'code' => 401,
        ], 'User not logged');

    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param array $data
     * @return
     */
    function register(Request $request)
    {
        $user = new User;
        $user->name = $request->input('name');
        $user->email = $request->input('email');
        $user->device_token = $request->input('device_token','');
        $user->password = Hash::make($request->input('password'));
        $user->api_token = str_random(60);
        $user->save();

        $user->assignRole('driver');

        $user->addMediaFromUrl("https://na.ui-avatars.com/api/?name=" . str_replace(" ", "+", $user->name))
            ->withCustomProperties(['uuid' => bcrypt(str_random())])
            ->toMediaCollection('avatar');
        event(new UserRoleChangedEvent($user));

        return $this->sendResponse($user, 'User retrieved successfully');
    }

    function logout(Request $request)
    {
        $user = $this->userRepository->findByField('api_token', $request->input('api_token'))->first();
        if (!$user) {
            return $this->sendResponse([
                'error' => true,
                'code' => 404,
            ], 'User not found');
        }
        try {
            auth()->logout();
        } catch (ValidatorException $e) {
            return $this->sendResponse([
                'error' => true,
                'code' => 404,
            ], 'User not found');
        }
        return $this->sendResponse($user['name'], 'User logout successfully');

    }

    function user(Request $request)
    {
        $user = $this->userRepository->findByField('api_token', $request->input('api_token'))->first();

        if (!$user) {
            return $this->sendResponse([
                'error' => true,
                'code' => 404,
            ], 'User not found');
        }

        return $this->sendResponse($user, 'User retrieved successfully');
    }

    function settings(Request $request)
    {
        $settings = setting()->all();
        $settings = array_intersect_key($settings,
            [
                'default_tax' => '',
                'default_currency' => '',
                'app_name' => '',
                'currency_right' => '',
                'enable_paypal' => '',
                'enable_stripe' => '',
                'main_color' => '',
                'main_dark_color' => '',
                'second_color' => '',
                'second_dark_color' => '',
                'accent_color' => '',
                'accent_dark_color' => '',
                'scaffold_dark_color' => '',
                'scaffold_color' => '',
                'google_maps_key' => '',
                'mobile_language' => '',
                'app_version' => '',
                'enable_version' => '',
                'distance_unit' => '',
            ]
        );
        Log::warning($settings);

        if (!$settings) {
            return $this->sendResponse([
                'error' => true,
                'code' => 404,
            ], 'Settings not found');
        }

        return $this->sendResponse($settings, 'Settings retrieved successfully');
    }

    /**
     * Update the specified User in storage.
     *
     * @param int $id
     * @param Request $request
     *
     * @return Response
     */
    public function update($id, Request $request)
    {
        $user = $this->userRepository->findWithoutFail($id);

        if (empty($user)) {
            return $this->sendResponse([
                'error' => true,
                'code' => 404,
            ], 'User not found');
        }
        $input = $request->except(['password', 'api_token']);
        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->userRepository->model());
        try {
            $user = $this->userRepository->update($input, $id);

            foreach (getCustomFieldsValues($customFields, $request) as $value) {
                $user->customFieldsValues()
                    ->updateOrCreate(['custom_field_id' => $value['custom_field_id']], $value);
            }
        } catch (ValidatorException $e) {
            return $this->sendResponse([
                'error' => true,
                'code' => 404,
            ], $e->getMessage());
        }

        return $this->sendResponse($user, __('lang.updated_successfully', ['operator' => __('lang.user')]));
    }

    function getOrder(Request $request)
    {
        $user = $this->userRepository->findByField('api_token', $request->input('api_token'))->first();

        if (!$user && !$user->hasRole('cashier')) {
            
           return ResponseJson(404,'error','User not found');

        }


        $orders= Order::where('order_status_id','1')->get();
        foreach($orders as $value){
            $value->setAttribute('user', username($value->user_id));
        }
         return ResponseJson(200,'success',$orders);
        
       // $order = new \App\Models\Order;
    //    $orders = $order->newQuery()->with("orderStatus")/*->with("user")->with('payment')*/
      //      ->join("food_orders", "orders.id", "=", "food_orders.order_id")
        //    ->join("foods", "foods.id", "=", "food_orders.food_id")
          //  ->join("user_restaurants", "user_restaurants.restaurant_id", "=", "foods.restaurant_id")
            //->where('user_restaurants.user_id', $user->id)
            //->groupBy('orders.id')
            //->select('orders.*')->get(); 

            // $order = new \App\Models\Order;
            // return $order->newQuery()->with("user")->with("orderStatus")->with('payment')
            //     ->join("food_orders", "orders.id", "=", "food_orders.order_id")
            //     ->join("foods", "foods.id", "=", "food_orders.food_id")
            //     ->join("delivery_addresses", "delivery_addresses.id", "=", "orders.delivery_address_id")
            //     ->join("user_restaurants", "user_restaurants.restaurant_id", "=", "foods.restaurant_id")
            //     ->where('user_restaurants.user_id', auth()->id())
            //     ->groupBy('orders.id')
            //     ->select(['orders.*','delivery_addresses.address','delivery_addresses.description'])->get(); 
        
        //return $this->sendResponse($orders, 'User retrieved successfully');
    }
    public function order_action(Request $request){
      $user = $this->userRepository->findByField('api_token', $request->input('api_token'))->first();
        
        if (!$user && !$user->hasRole('cashier')) {
            return $this->sendResponse([
                'error' => true,
                'code' => 404,
            ], 'User not found');
        }
        $action = OrderStatus::where('status',$request->action)->first();
        
         $order = Order::find($request->order_id)->with('orderStatus','user','foodOrders')->first();
         $order->order_status_id = $action->id;
         $order->save();
          return $this->sendResponse($order, 'User retrieved successfully');
                  return ResponseJson(200,'success','User retrieved successfully');

    }
    public function getOrderById(Request $request)
    {
        $user = $this->userRepository->findByField('api_token', $request->input('api_token'))->first();
        
        if (!$user && !$user->hasRole('cashier')) {
            return $this->sendResponse([
                'error' => true,
                'code' => 404,
            ], 'User not found');
        }
        $order = Order::where('id',$request->order_id)->first();
        $user = User::find($order->user_id);
        $food = FoodOrder::where('order_id',$order->id)->get();
        return ResponseJson(200,'success',['order'=>$order,'user'=>$user,'food'=>$food]);
        // $order = $this->orderRepository->findWithoutFail(105);
        // $subtotal = 0;
        // return $order->foodOrders;
        // $food_order = new \App\Models\FoodOrder;
        // $showOrders = $food_order->newQuery()->with("food")
        //     ->where('food_orders.order_id', $order->id)
        //     ->select('food_orders.*')->orderBy('food_orders.id', 'desc')->get();
        // $food_order = new \App\Models\order;
        // $showOrders = $food_order->newQuery()->with("foodOrders")->with("orderStatus")->with("payment")->with("deliveryAddress")
           //  ->where('id', 119)
            // ->select('orders.*')->first();

       // return $this->sendResponse(['order'=>$order,'user'=>$user,'food'=>$food], 'User retrieved successfully');
        // if (empty($order)) {
            
        // }
        // foreach ($order->foodOrders as $foodOrder) {
        //     $subtotal += $foodOrder->price * $foodOrder->quantity;
        // }

        // $total = $subtotal + $order['delivery_fee'];
        // // $total = $subtotal;
        // $total += ($total * ($order['tax'] / 100));
        // $foodOrderDataTable->id = $id;
        
        
        // $total = $total  - $order['order_discount'];
        // // $total = $total  + $order['delivery_fee'];
        // $discount = $order['order_discount'];

        // return $foodOrderDataTable->render('orders.show', ["order" => $order, "total" => $total,'discount'=>$discount, "subtotal" => $subtotal]);
    }

    function sendResetLinkEmail(Request $request)
    {
        $this->validate($request, ['email' => 'required|email']);

        $response = Password::broker()->sendResetLink(
            $request->only('email')
        );

        if($response == Password::RESET_LINK_SENT){
            return $this->sendResponse(true, 'Reset link was sent successfully');
        }else{
            return $this->sendError([
                'error' => 'Reset link not sent',
                'code' => 401,
            ], 'Reset link not sent');
        }

    }
    

}
