<?php

namespace App\Repositories;

use App\Models\RestaurantCouppon;
use InfyOm\Generator\Common\BaseRepository;

/**
 * Class RestaurantReviewRepository
 * @package App\Repositories
 * @version August 29, 2019, 9:39 pm UTC
 *
 * @method RestaurantReview findWithoutFail($id, $columns = ['*'])
 * @method RestaurantReview find($id, $columns = ['*'])
 * @method RestaurantReview first($columns = ['*'])
*/
class RestaurantCoupponRepository extends BaseRepository
{
    /**
     * @var array
     */
    protected $fieldSearchable = [
        'value',
        'code',
        'rest_id',
        'updated_at',
    ];

    /**
     * Configure the Model
     **/
    public function model()
    {
        return RestaurantCouppon::class;
    }
}
