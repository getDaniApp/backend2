<?php

namespace App\Http\Controllers;

use App\Criteria\Users\DriversCriteria;
use App\Criteria\Users\ManagersCriteria;
use App\DataTables\RestaurantDataTable;
use App\Events\RestaurantChangedEvent;
use App\Http\Requests\CreateRestaurantRequest;
use App\Http\Requests\UpdateRestaurantRequest;
use App\Repositories\CustomFieldRepository;
use App\Repositories\RestaurantRepository;
use App\Repositories\UploadRepository;
use App\Repositories\UserRepository;
use Flash;
use App\Models\Food;
use App\Repositories\FoodRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Prettus\Validator\Exceptions\ValidatorException;

class RestaurantController extends Controller
{
    /** @var  RestaurantRepository */
    private $restaurantRepository;

    /**
     * @var CustomFieldRepository
     */
    private $customFieldRepository;
    
    /** @var  FoodRepository */
    private $foodRepository;
    
    /**
     * @var UploadRepository
     */
    private $uploadRepository;
    /**
     * @var UserRepository
     */
    private $userRepository;

    public function __construct(FoodRepository $foodRepo,RestaurantRepository $restaurantRepo, CustomFieldRepository $customFieldRepo, UploadRepository $uploadRepo, UserRepository $userRepo)
    {
        parent::__construct();
        $this->restaurantRepository = $restaurantRepo;
        $this->customFieldRepository = $customFieldRepo;
        $this->uploadRepository = $uploadRepo;
        $this->userRepository = $userRepo;
        $this->foodRepository = $foodRepo;
    }

    /**
     * Display a listing of the Restaurant.
     *
     * @param RestaurantDataTable $restaurantDataTable
     * @return Response
     */
    public function index(RestaurantDataTable $restaurantDataTable)
    {
        return $restaurantDataTable->render('restaurants.index');
    }

    /**
     * Show the form for creating a new Restaurant.
     *
     * @return Response
     */
    public function create()
    {

        $user = $this->userRepository->getByCriteria(new ManagersCriteria())->pluck('name', 'id');
        $drivers = $this->userRepository->getByCriteria(new DriversCriteria())->pluck('name', 'id');
        $usersSelected = [];
        $driversSelected = [];
        $hasCustomField = in_array($this->restaurantRepository->model(), setting('custom_field_models', []));
        if ($hasCustomField) {
            $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->restaurantRepository->model());
            $html = generateCustomField($customFields);
        }
        return view('restaurants.create')->with("customFields", isset($html) ? $html : false)->with("user", $user)->with("drivers", $drivers)->with("usersSelected", $usersSelected)->with("driversSelected", $driversSelected);
    }

    /**
     * Store a newly created Restaurant in storage.
     *
     * @param CreateRestaurantRequest $request
     *
     * @return Response
     */
    public function store(CreateRestaurantRequest $request)
    {
        $input = $request->all();
        if (auth()->user()->hasRole('manager')) {
            $input['users'] = [auth()->id()];
        }
        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->restaurantRepository->model());
        try {
            $restaurant = $this->restaurantRepository->create($input);
            $restaurant->customFieldsValues()->createMany(getCustomFieldsValues($customFields, $request));
            if (isset($input['image']) && $input['image']) {
                $cacheUpload = $this->uploadRepository->getByUuid($input['image']);
                foreach ($this->uploadRepository->collectionsNames() as $mediaName => $value) {
                    
                    $mediaItem = $cacheUpload->getMedia($mediaName)->first();
                    if(isset($mediaItem) && $mediaItem) break;
                }
                $mediaItem->copy($restaurant, 'image');
            }
            event(new RestaurantChangedEvent($restaurant));
        } catch (ValidatorException $e) {
            Flash::error($e->getMessage());
        }

        Flash::success(__('lang.saved_successfully', ['operator' => __('lang.restaurant')]));

        return redirect(route('restaurants.index'));
    }

    /**
     * Display the specified Restaurant.
     *
     * @param int $id
     *
     * @return Response
     */
    public function show($id)
    {
        $restaurant = $this->restaurantRepository->findWithoutFail($id);

        if (empty($restaurant)) {
            Flash::error('Restaurant not found');

            return redirect(route('restaurants.index'));
        }

        return view('restaurants.show')->with('restaurant', $restaurant);
    }

    /**
     * Show the form for editing the specified Restaurant.
     *
     * @param int $id
     *
     * @return Response
     */
    public function edit($id)
    {
        $restaurant = $this->restaurantRepository->findWithoutFail($id);

        $user = $this->userRepository->getByCriteria(new ManagersCriteria())->pluck('name', 'id');
        $drivers = $this->userRepository->getByCriteria(new DriversCriteria())->pluck('name', 'id');


        $usersSelected = $restaurant->users()->pluck('users.id')->toArray();
        $driversSelected = $restaurant->drivers()->pluck('users.id')->toArray();

        if (empty($restaurant)) {
            Flash::error(__('lang.not_found', ['operator' => __('lang.restaurant')]));

            return redirect(route('restaurants.index'));
        }
        $customFieldsValues = $restaurant->customFieldsValues()->with('customField')->get();
        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->restaurantRepository->model());
        $hasCustomField = in_array($this->restaurantRepository->model(), setting('custom_field_models', []));
        if ($hasCustomField) {
            $html = generateCustomField($customFields, $customFieldsValues);
        }

        return view('restaurants.edit')->with('restaurant', $restaurant)->with("customFields", isset($html) ? $html : false)->with("user", $user)->with("drivers", $drivers)->with("usersSelected", $usersSelected)->with("driversSelected", $driversSelected);
    }
    
        public function clone($id)
    {

        // $foods = Food::where('restaurant_id', $id)->get();
        // return $foods[0]->media[0]->custom_properties['uuid'];

        $restaurantc = $this->restaurantRepository->findWithoutFail($id);
        // return $restaurantc->media[0]->custom_properties['uuid'];
        $restaurantc->replicate()->save();
        $last =  $this->restaurantRepository->latest()->first();
        try {
            
            if (isset($restaurantc->media[0]->custom_properties['uuid']) && $restaurantc->media[0]->custom_properties['uuid']) {
                $cacheUpload = $this->uploadRepository->getByUuid($restaurantc->media[0]->custom_properties['uuid']);
                $mediaItem = $cacheUpload->getMedia('image')->first();
                $mediaItem->copy($last, 'image');
            }

            $foods = Food::where('restaurant_id', $id)->get();

            if (isset($foods) && $foods){

                foreach ($foods as $key => $food) {
                    $last_food_ = new Food;
                    $last_food_->name = $food->name;
                    $last_food_->price = $food->price;
                    $last_food_->discount_price = $food->discount_price;
                    $last_food_->description = $food->description;
                    $last_food_->ingredients = $food->ingredients;
                    $last_food_->weight = $food->weight;
                    $last_food_->point = $food->point;
                    $last_food_->total_point = $food->total_point;
                    $last_food_->featured = $food->featured;
                    $last_food_->restaurant_id = $last->id;
                    $last_food_->category_id = $food->category_id;
                    $last_food_->save();
                    $last_food =  Food::latest()->first();
                    if (isset($food->media[0]->custom_properties['uuid']) && $food->media[0]->custom_properties['uuid']) {
                        $cacheUpload = $this->uploadRepository->getByUuid($food->media[0]->custom_properties['uuid']);
                        foreach ($this->uploadRepository->collectionsNames() as $mediaName => $value) {
                    
                            $mediaItem = $cacheUpload->getMedia($mediaName)->first();
                            if(isset($mediaItem) && $mediaItem) break;
                        }
                        $mediaItem->copy($last_food, 'image');
                    }
                }
            }
            // event(new RestaurantChangedEvent($last));
        } catch (ValidatorException $e) {
            Flash::error($e->getMessage());
        }

        Flash::success(__('lang.saved_successfully', ['operator' => __('lang.restaurant')]));

        return redirect(route('restaurants.index'));
    }

    /**
     * Update the specified Restaurant in storage.
     *
     * @param int $id
     * @param UpdateRestaurantRequest $request
     *
     * @return Response
     */
    public function update($id, UpdateRestaurantRequest $request)
    {
        $restaurant = $this->restaurantRepository->findWithoutFail($id);

        if (empty($restaurant)) {
            Flash::error('Restaurant not found');
            return redirect(route('restaurants.index'));
        }
        $input = $request->all();
        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->restaurantRepository->model());
        try {
            $restaurant = $this->restaurantRepository->update($input, $id);
            $input['users'] = isset($input['users']) ? $input['users'] : [];
            $input['drivers'] = isset($input['drivers']) ? $input['drivers'] : [];
            if (isset($input['image']) && $input['image']) {
                $cacheUpload = $this->uploadRepository->getByUuid($input['image']);
                foreach ($this->uploadRepository->collectionsNames() as $mediaName => $value) {
                    
                    $mediaItem = $cacheUpload->getMedia($mediaName)->first();
                    if(isset($mediaItem) && $mediaItem) break;
                }
                $mediaItem->copy($restaurant, 'image');
            }
            foreach (getCustomFieldsValues($customFields, $request) as $value) {
                $restaurant->customFieldsValues()
                    ->updateOrCreate(['custom_field_id' => $value['custom_field_id']], $value);
            }
            event(new RestaurantChangedEvent($restaurant));
        } catch (ValidatorException $e) {
            Flash::error($e->getMessage());
        }

        Flash::success(__('lang.updated_successfully', ['operator' => __('lang.restaurant')]));

        return redirect(route('restaurants.index'));
    }

    /**
     * Remove the specified Restaurant from storage.
     *
     * @param int $id
     *
     * @return Response
     */
    public function destroy($id)
    {
        $restaurant = $this->restaurantRepository->findWithoutFail($id);

        if (empty($restaurant)) {
            Flash::error('Restaurant not found');

            return redirect(route('restaurants.index'));
        }

        $this->restaurantRepository->delete($id);

        Flash::success(__('lang.deleted_successfully', ['operator' => __('lang.restaurant')]));

        return redirect(route('restaurants.index'));
    }

    /**
     * Remove Media of Restaurant
     * @param Request $request
     */
    public function removeMedia(Request $request)
    {
        $input = $request->all();
        $restaurant = $this->restaurantRepository->findWithoutFail($input['id']);
        try {
            if ($restaurant->hasMedia($input['collection'])) {
                $restaurant->getFirstMedia($input['collection'])->delete();
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }
}
