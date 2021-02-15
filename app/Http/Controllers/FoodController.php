<?php

namespace App\Http\Controllers;

use App\DataTables\FoodDataTable;
use App\Http\Requests;
use App\Http\Requests\CreateFoodRequest;
use App\Http\Requests\UpdateFoodRequest;
use App\Repositories\FoodRepository;
use App\Repositories\CustomFieldRepository;
use App\Repositories\UploadRepository;
use App\Repositories\RestaurantRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\ExtraRepository;
use Flash;
use App\Models\Extra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Prettus\Validator\Exceptions\ValidatorException;

class FoodController extends Controller
{
    /** @var  FoodRepository */
    private $foodRepository;

    /**
     * @var CustomFieldRepository
     */
    private $customFieldRepository;

    /**
     * @var UploadRepository
     */
    private $uploadRepository;
    /**
     * @var RestaurantRepository
     */
    private $restaurantRepository;
    /**
     * @var CategoryRepository
     */
    private $categoryRepository;
    
    /** @var  ExtraRepository */
    private $extraRepository;

    public function __construct(ExtraRepository $extraRepo,FoodRepository $foodRepo, CustomFieldRepository $customFieldRepo, UploadRepository $uploadRepo
        , RestaurantRepository $restaurantRepo
        , CategoryRepository $categoryRepo)
    {
        parent::__construct();
        $this->foodRepository = $foodRepo;
        $this->customFieldRepository = $customFieldRepo;
        $this->extraRepository = $extraRepo;
        $this->uploadRepository = $uploadRepo;
        $this->restaurantRepository = $restaurantRepo;
        $this->categoryRepository = $categoryRepo;
    }

    /**
     * Display a listing of the Food.
     *
     * @param FoodDataTable $foodDataTable
     * @return Response
     */
    public function index(FoodDataTable $foodDataTable)
    {
        return $foodDataTable->render('foods.index');
    }

    /**
     * Show the form for creating a new Food.
     *
     * @return Response
     */
    public function create()
    {

        $category = $this->categoryRepository->pluck('name', 'id');
        if (auth()->user()->hasRole('admin')){
            $restaurant = $this->restaurantRepository->pluck('name', 'id');
        }else{
            $restaurant = $this->restaurantRepository->myRestaurants()->pluck('name', 'id');
        }
        $hasCustomField = in_array($this->foodRepository->model(), setting('custom_field_models', []));
        $extras = $this->extraRepository->groupBy('name')->pluck('name', 'id');
        // $extras[] = 'All - الجميع';
        // array_unshift($extras,"All - الجميع");
        $extraSelected = [];
        if ($hasCustomField) {
            $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->foodRepository->model());
            $html = generateCustomField($customFields);
        }
        return view('foods.create')->with("customFields", isset($html) ? $html : false)->with("extras", $extras->reverse())->with("extraSelected", $extraSelected)->with("restaurant", $restaurant)->with("category", $category);
    }

    /**
     * Store a newly created Food in storage.
     *
     * @param CreateFoodRequest $request
     *
     * @return Response
     */
    public function store(CreateFoodRequest $request)
    {
        $input = $request->all();
        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->foodRepository->model());
        try {
            $food = $this->foodRepository->create($input);
            $food->customFieldsValues()->createMany(getCustomFieldsValues($customFields, $request));
            if (isset($input['image']) && $input['image']) {
                $cacheUpload = $this->uploadRepository->getByUuid($input['image']);
                foreach ($this->uploadRepository->collectionsNames() as $mediaName => $value) {
                    
                    $mediaItem = $cacheUpload->getMedia($mediaName)->first();
                    if(isset($mediaItem) && $mediaItem) break;
                }
                $mediaItem->copy($food, 'image');
            }
        } catch (ValidatorException $e) {
            Flash::error($e->getMessage());
        }

        Flash::success(__('lang.saved_successfully', ['operator' => __('lang.food')]));

        return redirect(route('foods.index'));
    }

    /**
     * Display the specified Food.
     *
     * @param int $id
     *
     * @return Response
     */
    public function show($id)
    {
        $food = $this->foodRepository->findWithoutFail($id);

        if (empty($food)) {
            Flash::error('Food not found');

            return redirect(route('foods.index'));
        }

        return view('foods.show')->with('food', $food);
    }

    /**
     * Show the form for editing the specified Food.
     *
     * @param int $id
     *
     * @return Response
     */
    public function edit($id)
    {
        $food = $this->foodRepository->findWithoutFail($id);
        if (empty($food)) {
            Flash::error(__('lang.not_found', ['operator' => __('lang.food')]));

            return redirect(route('foods.index'));
        }
        $category = $this->categoryRepository->pluck('name', 'id');
        if (auth()->user()->hasRole('admin')){
            $restaurant = $this->restaurantRepository->pluck('name', 'id');
        }else{
            $restaurant = $this->restaurantRepository->myRestaurants()->pluck('name', 'id');
        }
        $extras = $this->extraRepository->pluck('name', 'id');
        $extraSelected = $food->extras()->pluck('id')->toArray();
        $customFieldsValues = $food->customFieldsValues()->with('customField')->get();
        $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->foodRepository->model());
        $hasCustomField = in_array($this->foodRepository->model(), setting('custom_field_models', []));
        if ($hasCustomField) {
            $html = generateCustomField($customFields, $customFieldsValues);
        }

        return view('foods.edit')->with('food', $food)->with("extras", $extras->reverse())->with("extraSelected", $extraSelected)->with("customFields", isset($html) ? $html : false)->with("restaurant", $restaurant)->with("category", $category);
    }

    /**
     * Update the specified Food in storage.
     *
     * @param int $id
     * @param UpdateFoodRequest $request
     *
     * @return Response
     */
    // public function update($id, UpdateFoodRequest $request)
    // {
    //     $food = $this->foodRepository->findWithoutFail($id);

    //     if (empty($food)) {
    //         Flash::error('Food not found');
    //         return redirect(route('foods.index'));
    //     }
    //     $input = $request->all();
    //     $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->foodRepository->model());
    //     try {
    //         $food = $this->foodRepository->update($input, $id);

    //         if (isset($input['image']) && $input['image']) {
    //             $cacheUpload = $this->uploadRepository->getByUuid($input['image']);
    //             foreach ($this->uploadRepository->collectionsNames() as $mediaName => $value) {
                    
    //                 $mediaItem = $cacheUpload->getMedia($mediaName)->first();
    //                 if(isset($mediaItem) && $mediaItem) break;
    //             }
    //             $mediaItem->copy($food, 'image');
    //         }
    //         foreach (getCustomFieldsValues($customFields, $request) as $value) {
    //             $food->customFieldsValues()
    //                 ->updateOrCreate(['custom_field_id' => $value['custom_field_id']], $value);
    //         }
    //     } catch (ValidatorException $e) {
    //         Flash::error($e->getMessage());
    //     }

    //     Flash::success(__('lang.updated_successfully', ['operator' => __('lang.food')]));

    //     return redirect(route('foods.index'));
    // }
    
    public function update($id, UpdateFoodRequest $request)
    {
        // return $request['extras'];
        $food = $this->foodRepository->findWithoutFail($id);

        if (empty($food)) {
            Flash::error('Food not found');
            return redirect(route('foods.index'));
        }
        $input = $request->all();
        // $customFields = $this->customFieldRepository->findByField('custom_field_model', $this->foodRepository->model());

        try {
            $food = $this->foodRepository->update($input, $id);
            if (isset($input['image']) && $input['image']) {
                $cacheUpload = $this->uploadRepository->getByUuid($input['image']);
                
                foreach ($this->uploadRepository->collectionsNames() as $mediaName => $value) {
                    
                    $mediaItem = $cacheUpload->getMedia($mediaName)->first();
                    if(isset($mediaItem) && $mediaItem) break;
                }
                // dd($cacheUpload->getMedia('image'));
                $mediaItem->copy($food, 'image');
            }
            // foreach (getCustomFieldsValues($customFields, $request) as $value) {
            //     $food->customFieldsValues()
            //         ->updateOrCreate(['custom_field_id' => $value['custom_field_id']], $value);
            // }
            if(isset($request['extras']) && $request['extras']){
                foreach ($request['extras'] as $key => $value) {
                    try {
                        
                            $ext = $this->extraRepository->findWithoutFail($value);
                            $check = Extra::where([
                                'name'=>$ext->name,
                                'price'=>$ext->price,
                                'description'=>$ext->description,
                                'food_id'=>$id,
                            ])->get()->count();
                            
                            if($check>0) continue;
    
                            $new_ext = new Extra;
                            $new_ext->food_id = $id;
                            $new_ext->name = $ext->name;
                            $new_ext->price = $ext->price;
                            $new_ext->description = $ext->description;
                            $new_ext->save();
                            $new_ =  Extra::latest()->first();
                            if (isset($ext->media[0]->custom_properties['uuid']) && $ext->media[0]->custom_properties['uuid']) {
                                $cacheUpload = $this->uploadRepository->getByUuid($ext->media[0]->custom_properties['uuid']);
                                foreach ($this->uploadRepository->collectionsNames() as $mediaName => $value) {
                            
                                    $mediaItem = $cacheUpload->getMedia($mediaName)->first();
                                    if(isset($mediaItem) && $mediaItem) break;
                                }
                                $mediaItem->copy($new_, 'image');
                            }
                            
                        
                    } catch (\Throwable $th) {
                        continue;
                    }
                }
            }

            
        } catch (ValidatorException $e) {
            Flash::error($e->getMessage());
        }

        Flash::success(__('lang.updated_successfully', ['operator' => __('lang.food')]));

        return redirect(route('foods.index'));
    }

    /**
     * Remove the specified Food from storage.
     *
     * @param int $id
     *
     * @return Response
     */
    public function destroy($id)
    {
        $food = $this->foodRepository->findWithoutFail($id);

        if (empty($food)) {
            Flash::error('Food not found');

            return redirect(route('foods.index'));
        }

        $this->foodRepository->delete($id);

        Flash::success(__('lang.deleted_successfully', ['operator' => __('lang.food')]));

        return redirect(route('foods.index'));
    }

    /**
     * Remove Media of Food
     * @param Request $request
     */
    public function removeMedia(Request $request)
    {
        $input = $request->all();
        $food = $this->foodRepository->findWithoutFail($input['id']);
        try {
            if ($food->hasMedia($input['collection'])) {
                $food->getFirstMedia($input['collection'])->delete();
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }
}
