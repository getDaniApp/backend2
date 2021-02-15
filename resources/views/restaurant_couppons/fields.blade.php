@if($customFields)
<h5 class="col-12 pb-4">{!! trans('lang.main_fields') !!}</h5>
@endif
<div style="flex: 50%;max-width: 50%;padding: 0 4px;" class="column">
<!-- code Field -->
<div class="form-group row ">
  {!! Form::label('code', trans("lang.coupon_name"), ['class' => 'col-3 control-label text-right']) !!}
  <div class="col-9">
    {!! Form::text('code', null, ['class' => 'form-control','placeholder'=>
     trans("lang.coupon_name_insert")  ]) !!}
    <div class="form-text text-muted">{{ trans("lang.coupon_name_insert") }}</div>
  </div>
</div>

<!-- value Field -->
<div class="form-group row ">
  {!! Form::label('value', trans("lang.coupon_value"), ['class' => 'col-3 control-label text-right']) !!}
  <div class="col-9">
    {!! Form::text('value', null,  ['class' => 'form-control','placeholder'=>  trans("lang.coupon_value_insert")]) !!}
    <div class="form-text text-muted">
      {{ trans("lang.coupon_value_insert") }}
    </div>
  </div>
</div>
</div>


<div style="flex: 50%;max-width: 50%;padding: 0 4px;" class="column">

<!-- start Field -->
<div class="form-group row ">
  {!! Form::label('start', trans("lang.coupon_value_start"), ['class' => 'col-3 control-label text-right']) !!}
  <div class="col-9">
    {!! Form::text('start', null,  ['class' => 'form-control','placeholder'=>  trans("lang.coupon_value_start")]) !!}
    <div class="form-text text-muted">
      {{ trans("lang.coupon_value_start_help") }}
    </div>
  </div>
</div>


<!-- Restaurant Id Field -->
<div class="form-group row ">
  {!! Form::label('rest_id', trans("lang.restaurant_review_restaurant_id"),['class' => 'col-3 control-label text-right']) !!}
  <div class="col-9">
    {!! Form::select('rest_id', $restaurant, null, ['class' => 'select2 form-control']) !!}
    <div class="form-text text-muted">{{ trans("lang.restaurant_review_restaurant_id_help") }}</div>
  </div>
</div>

</div>
@if($customFields)
<div class="clearfix"></div>
<div class="col-12 custom-field-container">
  <h5 class="col-12 pb-4">{!! trans('lang.custom_field_plural') !!}</h5>
  {!! $customFields !!}
</div>
@endif
<!-- Submit Field -->
<div class="form-group col-12 text-right">
  <button type="submit" class="btn btn-{{setting('theme_color')}}" ><i class="fa fa-save"></i> {{trans('lang.save')}} {{trans('lang.restaurant_coupon')}}</button>
  <a href="{!! route('restaurantCouppons.index') !!}" class="btn btn-default"><i class="fa fa-undo"></i> {{trans('lang.cancel')}}</a>
</div>
