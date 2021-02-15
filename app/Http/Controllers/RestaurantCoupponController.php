<?php

namespace App\Http\Controllers;

use App\DataTables\RestaurantCoupponDataTable;
use App\Http\Requests;
use App\Http\Requests\CreateRestaurantCoupponRequest; 
use App\Http\Requests\UpdateRestaurantCouponRequest;
use App\Repositories\RestaurantCoupponRepository;
use App\Repositories\CustomFieldRepository;
use App\Repositories\UserRepository;
use App\Repositories\RestaurantRepository;
use Flash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Prettus\Validator\Exceptions\ValidatorException;

class RestaurantCoupponController extends Controller
{
    /** @var  RestaurantCoupponRepository */
    private $restaurantCoupponRepository;

    /**
     * @var CustomFieldRepository
     */
    private $customFieldRepository;

    /**
  * @var UserRepository
  */
private $userRepository;/**
  * @var RestaurantRepository
  */
private $restaurantRepository;

    public function __construct(RestaurantCoupponRepository $restaurantCoupponRepo, CustomFieldRepository $customFieldRepo , UserRepository $userRepo
                , RestaurantRepository $restaurantRepo)
    {
        parent::__construct();
        $this->restaurantCoupponRepository = $restaurantCoupponRepo;
        $this->customFieldRepository = $customFieldRepo;
        $this->userRepository = $userRepo;
        $this->restaurantRepository = $restaurantRepo;
    }

    /**
     * Display a listing of the RestaurantReview.
     *
     * @param RestaurantCoupponDataTable $restaurantReviewDataTable
     * @return Response
     */
    public function index(RestaurantCoupponDataTable $restaurantCoupponDataTable)
    {
        return $restaurantCoupponDataTable->render('restaurant_couppons.index');
    }

    /**
     * Show the form for creating a new RestaurantReview.
     *
     * @return Response
     */
    public function create()
    {
        $user = $this->userRepository->pluck('name','id');

        if (auth()->user()->hasRole('admin')){
            $restaurant = $this->restaurantRepository->pluck('name', 'id');
        }else{
            $restaurant = $this->restaurantRepository->myRestaurants()->pluck('name', 'id');
        }
        
        $hasCustomField = in_array($this->restaurantCoupponRepository->model(),setting('custom_field_models',[]));
            if($hasCustomField){
                $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->restaurantCoupponRepository->model());
                $html = generateCustomField($customFields);
            }
        return view('restaurant_couppons.create')->with("customFields", isset($html) ? $html : false)
                    ->with("user",$user)->with("restaurant",$restaurant);
    }

    /**
     * Store a newly created RestaurantReview in storage.
     *
     * @param CreateRestaurantCoupponRequest $request
     *
     * @return Response
     */
    public function store(CreateRestaurantCoupponRequest $request)
    {
        $input = $request->all();
        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->restaurantCoupponRepository->model());
        try {
            $restaurantReview = $this->restaurantCoupponRepository->create($input);
            $restaurantReview->customFieldsValues()->createMany(getCustomFieldsValues($customFields,$request));
            
        } catch (ValidatorException $e) {
            Flash::error($e->getMessage());
        }

        Flash::success(__('lang.saved_successfully',['operator' => __('lang.restaurant_review')]));

        return redirect(route('restaurantCouppons.index'));
    }

    /**
     * Display the specified RestaurantReview.
     *
     * @param  int $id
     *
     * @return Response
     */
    public function show($id)
    {
        $restaurantReview = $this->restaurantCoupponRepository->findWithoutFail($id);

        if (empty($restaurantReview)) {
            Flash::error('Restaurant Review not found');

            return redirect(route('restaurantCouppons.index'));
        }

        return view('restaurant_reviews.show')->with('restaurantReview', $restaurantReview);
    }

    /**
     * Show the form for editing the specified RestaurantReview.
     *
     * @param  int $id
     *
     * @return Response
     */
    public function edit($id)
    {
        $restaurantReview = $this->restaurantCoupponRepository->findWithoutFail($id);
        // $user = $this->userRepository->pluck('name','id');
        if (auth()->user()->hasRole('admin')){
            $restaurant = $this->restaurantRepository->pluck('name', 'id');
        }else{
            $restaurant = $this->restaurantRepository->myRestaurants()->pluck('name', 'id');
        }
         

        if (empty($restaurantReview)) {
            Flash::error(__('lang.not_found',['operator' => __('lang.restaurant_coupon')]));

            return redirect(route('restaurantCouppons.index'));
        }
        $customFieldsValues = $restaurantReview->customFieldsValues()->with('customField')->get();
        $customFields =  $this->customFieldRepository->findByField('custom_field_model', $this->restaurantCoupponRepository->model());
        $hasCustomField = in_array($this->restaurantCoupponRepository->model(),setting('custom_field_models',[]));
        if($hasCustomField) {
            $html = generateCustomField($customFields, $customFieldsValues);
        }

        return view('restaurant_couppons.edit')->with('restaurantReview', $restaurantReview)->with("customFields", isset($html) ? $html : false)
        ->with("restaurant",$restaurant);
    }

    /**
     * Update the specified RestaurantReview in storage.
     *
     * @param  int              $id
     * @param UpdateRestaurantCouponRequest $request
     *
     * @return Response
     */
    public function update($id, UpdateRestaurantCouponRequest $request)
    {
        $restaurantReview = $this->restaurantCoupponRepository->findWithoutFail($id);

        if (empty($restaurantReview)) {
            Flash::error('Restaurant Review not found');
            return redirect(route('restaurantCouppons.index'));
        }
        $input = $request->all();
        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->restaurantCoupponRepository->model());
        try {
            $restaurantReview = $this->restaurantCoupponRepository->update($input, $id);
            
            
            foreach (getCustomFieldsValues($customFields, $request) as $value){
                $restaurantReview->customFieldsValues()
                    ->updateOrCreate(['custom_field_id'=>$value['custom_field_id']],$value);
            }
        } catch (ValidatorException $e) {
            Flash::error($e->getMessage());
        }

        Flash::success(__('lang.updated_successfully',['operator' => __('lang.restaurant_coupon')]));

        return redirect(route('restaurantCouppons.index'));
    }

    /**
     * Remove the specified RestaurantReview from storage.
     *
     * @param  int $id
     *
     * @return Response
     */
    public function destroy($id)
    {
        $restaurantCouppon = $this->restaurantCoupponRepository->findWithoutFail($id);

        if (empty($restaurantCouppon)) {
            Flash::error('Restaurant Coupon not found');

            return redirect(route('restaurantCouppons.index'));
        }

        $this->restaurantCoupponRepository->delete($id);

        Flash::success(__('lang.deleted_successfully',['operator' => __('lang.restaurant_coupon')]));

        return redirect(route('restaurantCouppons.index'));
    }

        /**
     * Remove Media of RestaurantReview
     * @param Request $request
     */
    public function removeMedia(Request $request)
    {
        $input = $request->all();
        $restaurantReview = $this->restaurantCoupponRepository->findWithoutFail($input['id']);
        try {
            if($restaurantReview->hasMedia($input['collection'])){
                $restaurantReview->getFirstMedia($input['collection'])->delete();
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }
}
