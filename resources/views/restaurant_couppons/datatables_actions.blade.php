<div class='btn-group btn-group-sm'>
  @can('restaurantCouppons.show')
  <a data-toggle="tooltip" data-placement="bottom" title="{{trans('lang.view_details')}}" href="{{ route('restaurantCouppons.show', $id) }}" class='btn btn-link'>
    <i class="fa fa-eye"></i>
  </a>
  @endcan

  @can('restaurantCouppons.edit')
  <a data-toggle="tooltip" data-placement="bottom" title="{{trans('lang.restaurant_review_edit')}}" href="{{ route('restaurantCouppons.edit', $id) }}" class='btn btn-link'>
    <i class="fa fa-edit"></i>
  </a>
  @endcan

  @can('restaurantCouppons.destroy')
{!! Form::open(['route' => ['restaurantCouppons.destroy', $id], 'method' => 'delete']) !!}
  {!! Form::button('<i class="fa fa-trash"></i>', [
  'type' => 'submit',
  'class' => 'btn btn-link text-danger',
  'onclick' => "return confirm('Are you sure?')"
  ]) !!}
{!! Form::close() !!}
  @endcan
</div>
