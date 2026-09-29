<x-master-layout :assets="$assets ?? []">
    <div>
        <?php $id = $id ?? null;?>
        @if(isset($id))
            {!! Form::model($data, ['route' => ['driver.update', $id], 'method' => 'patch' , 'enctype' => 'multipart/form-data']) !!}
        @else
            {!! Form::open(['route' => ['driver.store'], 'method' => 'post', 'enctype' => 'multipart/form-data']) !!}
        @endif
        <div class="row">
            <div class="col-12">
                <a href="{{route('driver.index')}}" class="btn border-radius-10 btn-dark float-right" role="button"><i class="fas fa-arrow-circle-left"></i> {{ __('message.back') }}</a>
            </div>
            <div class="col-xl-3 col-lg-4 mt-3">
                <div class="card border-radius-20">
                    <div class="card-header d-flex justify-content-between" style="border-top-left-radius: 20px; border-top-right-radius: 20px;">
                        <div class="header-title">
                            <h4 class="card-title">{{ $pageTitle }}</h4>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <div class="crm-profile-img-edit position-relative text-center">
                                <img src="{{ $profileImage ?? asset('images/user/1.jpg')}}" alt="User-Profile" class="crm-profile-pic rounded-circle avatar-100">
                                <div class="crm-p-image bg-primary">
                                    <svg class="upload-button" width="14" height="18" viewBox="0 0 24 24">
                                        <path fill="#ffffff" d="M14.06,9L15,9.94L5.92,19H5V18.08L14.06,9M17.66,3C17.41,3 17.15,3.1 16.96,3.29L15.13,5.12L18.88,8.87L20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18.17,3.09 17.92,3 17.66,3M14.06,6.19L3,17.25V21H6.75L17.81,9.94L14.06,6.19Z" />
                                    </svg>
                                    <input class="file-upload" type="file" accept="image/*" name="profile_image">
                                </div>
                            </div>
                            <div class="img-extension mt-3">
                                <div class="d-inline-block align-items-center">
                                    <span>{{ __('message.only') }}</span>
                                    @foreach(config('constant.IMAGE_EXTENTIONS') as $extention)
                                        <a href="javascript:void();">.{{ $extention }}</a>
                                    @endforeach
                                    <span>{{ __('message.allowed') }}</span>
                                </div>
                                <hr>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ __('message.status') }}</label>
                            <div class="row" style="--bs-gap: 1rem;">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        {{ Form::radio('status', 'active', old('status') === 'active', ['class' => 'form-check-input', 'id' => 'status-active']) }}
                                        {{ Form::label('status-active', __('message.active'), ['class' => 'form-check-label']) }}
                                    </div>
                                    <div class="form-check">
                                        {{ Form::radio('status', 'inactive', old('status') === 'inactive', ['class' => 'form-check-input', 'id' => 'status-inactive']) }}
                                        {{ Form::label('status-inactive', __('message.inactive'), ['class' => 'form-check-label']) }}
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        {{ Form::radio('status', 'pending', old('status') === 'pending', ['class' => 'form-check-input', 'id' => 'status-pending']) }}
                                        {{ Form::label('status-pending', __('message.pending'), ['class' => 'form-check-label']) }}
                                    </div>
                                    <div class="form-check">
                                        {{ Form::radio('status', 'banned', old('status') === 'banned', ['class' => 'form-check-input', 'id' => 'status-banned']) }}
                                        {{ Form::label('status-banned', __('message.banned'), ['class' => 'form-check-label']) }}
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        {{ Form::radio('status', 'reject', old('status') , ['class' => 'form-check-input', 'id' => 'status-reject' ]) }}
                                        {{ Form::label('status-reject', __('message.reject'), ['class' => 'form-check-label' ]) }}
                                    </div>      
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-9 col-lg-8 mt-3">
                <div class="card border-radius-20">
                    <div class="card-header d-flex justify-content-between" style="border-top-left-radius: 20px; border-top-right-radius: 20px;">
                        <div class="header-title">
                            <h4 class="card-title">{{ $pageTitle }} {{ __('message.information') }}</h4>
                        </div>
                        <div class="card-action">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="new-user-info">
                            <div class="row">
                                <div class="form-group col-md-6">
                                    {{ Form::label('first_name',__('message.first_name').' <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
                                    {{ Form::text('first_name',old('first_name'),['placeholder' => __('message.first_name'),'class' =>'form-control','required']) }}
                                </div>

                                <div class="form-group col-md-6">
                                    {{ Form::label('last_name',__('message.last_name').' <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
                                    {{ Form::text('last_name',old('last_name'),['placeholder' => __('message.last_name'),'class' =>'form-control','required']) }}
                                </div>
                                
                                <div class="form-group col-md-6">
                                    {{ Form::label('email',__('message.email').' <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
                                    {{ Form::email('email', old('email'), [ 'placeholder' => __('message.email'), 'class' => 'form-control', 'required' ]) }}
                                </div>

                                <div class="form-group col-md-6">
                                    {{ Form::label('username',__('message.username').' <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
                                    {{ Form::text('username', old('username'), ['class' => 'form-control', 'required', 'placeholder' => __('message.username') ]) }}
                                </div>

                                @if(!isset($id))
                                    <div class="form-group col-md-6">
                                        {{ Form::label('password',__('message.password').' <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
                                        <div class="input-group">
                                            {{ Form::password('password', ['class' => 'form-control', 'placeholder' =>  __('message.password') ]) }}
                                            <div class="input-group-append">
                                                <span class="input-group-text toggle-password" data-toggle="#password" style="cursor: pointer;">
                                                    <i class="fas fa-eye-slash"></i>
                                                </span>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">
                                            Password must be at least 8 characters, include one uppercase letter, one number and one special character.
                                        </small>
                                    </div>
                                @endif

                                <div class="form-group col-md-6">
                                    {{ Form::label('contact_number',__('message.contact_number').' <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
                                    {{ Form::text('contact_number', old('contact_number'),[ 'placeholder' => __('message.contact_number'), 'class' => 'form-control', 'id' => 'phone' ]) }}
                                </div>

                                <div class="form-group col-md-6">
                                    {{ Form::label('gender',__('message.gender').' <span class="text-danger">*</span>',['class'=>'form-control-label'],false) }}
                                    {{ Form::select('gender',[ 'male' => __('message.male') ,'female' => __('message.female') , 'other' => __('message.other') ], old('gender') ,[ 'class' =>'form-control select2js','required']) }}
                                </div>
                                {{--
                                @if(auth()->user()->hasAnyRole(['admin','demo_admin']))
                                <div class="form-group col-md-6">
                                    {{ Form::label('fleet_id', __('message.fleet'), ['class' => 'form-control-label']) }}
                                    {{ Form::select('fleet_id', isset($id) ? [ optional($data->fleet)->id => optional($data->fleet)->display_name ] : [] , old('fleet_id') , [
                                        'data-ajax--url' => route('ajax-list', [ 'type' => 'fleet' ]),
                                        'data-placeholder' => __('message.select_field', [ 'name' => __('message.fleet') ]),
                                        'class' =>'form-control select2js'
                                        ])
                                    }}
                                </div>
                                @endif
                                --}}

                                 <!-- Service review: admin approves / rejects the driver's requested services -->
                                <div class="form-group col-md-12">
                                    <label class="form-control-label">{{ __('message.service') }}</label>
                                    {{ Form::hidden('service_ids_submitted', 1) }}
                                    @if(isset($id) && $data->driverServices->count() > 0)
                                        <div class="table-responsive">
                                            <table class="table table-bordered mb-2">
                                                <thead>
                                                    <tr><th>{{ __('message.service') }}</th><th>Status</th><th>Action</th></tr>
                                                </thead>
                                                <tbody>
                                                @foreach($data->driverServices->sortBy('service_id') as $ds)
                                                    <tr>
                                                        <td>{{ optional($ds->service)->name ?? ('#'.$ds->service_id) }}</td>
                                                        <td>
                                                            @if($ds->status)
                                                                <span class="badge text-success badge-light-success">Approved</span>
                                                                <small class="text-muted">({{ $ds->is_active ? 'active' : 'inactive' }} today)</small>
                                                            @else
                                                                <span class="badge text-warning badge-light-warning">Requested by driver - pending</span>
                                                            @endif
                                                        </td>
                                                        <td style="min-width:200px">
                                                            <select name="service_action[{{ $ds->service_id }}]" class="form-control">
                                                                @if($ds->status)
                                                                    <option value="">Keep approved</option>
                                                                    <option value="remove">Remove (unapprove)</option>
                                                                @else
                                                                    <option value="">Keep pending</option>
                                                                    <option value="approve">Approve</option>
                                                                    <option value="reject">Reject</option>
                                                                @endif
                                                            </select>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <p class="text-muted mb-2">No services requested or approved yet.</p>
                                    @endif
                                    <label class="form-control-label">Add service (approved immediately)</label>
                                    {{ Form::select('add_service_id[]', [], null, [
                                            'class' => 'select2js form-group service',
                                            'multiple' => 'multiple',
                                            'data-placeholder' => __('message.select_name',[ 'select' => __('message.service') ]),
                                            'data-ajax--url' => route('ajax-list', ['type' => 'service']),
                                        ])
                                    }}
                                </div>
                                <div class="form-group col-md-6">
                                    <!-- {{ Form::label('car_model',__('message.car_model').' <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
                                    {{ Form::text('userDetail[car_model]', old('userDetail[car_model]'), ['class' => 'form-control', 'placeholder' => __('message.car_model')]) }} -->

                                    {{ Form::label('userDetail[car_model]', __('message.car_model').' <span class="text-danger">*</span>', ['class' => 'form-control-label'], false) }}

                                    {{ Form::select('userDetail[car_model]', 
                                        isset($id) ? [ optional($data->userDetail)->car_model => optional($data->userDetail)->car_model ] : [], 
                                        old('userDetail[car_model]'), 
                                        [
                                            'class' => 'select2js form-group car-model',
                                            'data-placeholder' => __('message.select_name', [ 'select' => __('message.car_model') ]),
                                            'data-ajax--url' => route('ajax-list', ['type' => 'car_model_name']),
                                        ])
                                    }}
                                </div>

                                <div class="form-group col-md-6">
                                    {{ Form::label('car_color',__('message.car_color').' <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
                                    {{ Form::text('userDetail[car_color]', old('userDetail[car_color]'), ['class' => 'form-control', 'placeholder' => __('message.car_color')]) }}
                                </div>
                                
                                <div class="form-group col-md-6">
                                    {{ Form::label('car_plate_number',__('message.car_plate_number').' <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
                                    {{ Form::text('userDetail[car_plate_number]', old('userDetail[car_plate_number]'), ['class' => 'form-control', 'placeholder' => __('message.car_plate_number')]) }}
                                </div>
                                
                                <div class="form-group col-md-6">
                                    <!-- {{ Form::label('car_production_year',__('message.car_production_year').' <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
                                    {{ Form::text('userDetail[car_production_year]', old('userDetail[car_production_year]'), ['class' => 'form-control', 'placeholder' => __('message.car_production_year')]) }} -->
                                    @php
                                        $currentYear = now()->year;
                                        $years = array_combine(range($currentYear, 2010), range($currentYear, 2010)); // [2025 => 2025, ..., 2010 => 2010]
                                    @endphp

                                    {{ Form::label('car_production_year', __('message.car_production_year') . ' <span class="text-danger">*</span>', ['class' => 'form-control-label'], false) }}

                                    {{ Form::select('userDetail[car_production_year]', $years, old('userDetail[car_production_year]'), [
                                        'class' => 'form-control',
                                        'placeholder' => __('message.select_name', ['select' => __('message.car_production_year')])
                                    ]) }}
                                </div>

                                <div class="form-group col-md-6">
                                    {{ Form::label('bank_name',__('message.bank_name').' <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
                                    {{ Form::text('userBankAccount[bank_name]', old('userBankAccount[bank_name]'), ['class' => 'form-control', 'placeholder' => __('message.bank_name')]) }}
                                </div>

                                <div class="form-group col-md-6">
                                    {{ Form::label('bank_code',__('message.bank_code').' <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
                                    {{ Form::text('userBankAccount[bank_code]', old('userBankAccount[bank_code]'), ['class' => 'form-control', 'placeholder' => __('message.bank_code')]) }}
                                </div>

                                <div class="form-group col-md-6">
                                    {{ Form::label('account_holder_nameaccount_holder_name',__('message.account_holder_name').' <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
                                    {{ Form::text('userBankAccount[account_holder_name]', old('userBankAccount[account_holder_name]'), ['class' => 'form-control', 'placeholder' => __('message.account_holder_name')]) }}
                                </div>

                                <div class="form-group col-md-6">
                                    {{ Form::label('account_number',__('message.account_number').' <span class="text-danger">*</span>',['class'=>'form-control-label'], false ) }}
                                    {{ Form::text('userBankAccount[account_number]', old('userBankAccount[account_number]'), ['class' => 'form-control', 'placeholder' => __('message.account_number')]) }}
                                </div>

                                <div class="form-group col-md-6">
                                    {{ Form::label('routing_number',__('message.routing_number'),['class'=>'form-control-label'], false ) }}
                                    {{ Form::text('userBankAccount[routing_number]', old('userBankAccount[routing_number]'), ['class' => 'form-control', 'placeholder' => __('message.routing_number')]) }}
                                </div>
                                
                                <div class="form-group col-md-6">
                                    {{ Form::label('account_type',__('message.account_type'),['class'=>'form-control-label'], false ) }}
                                    {{ Form::select(
                                        'userBankAccount[account_type]',
                                        [ 'checking' => __('message.checking'), 'savings' => __('message.savings') ],
                                        old('userBankAccount.account_type'),
                                        [ 'class' => 'form-control select2js', 'required' ]
                                    ) }}
                                </div>

                                <!-- <div class="form-group col-md-6">
                                    {{ Form::label('bank_iban',__('message.bank_iban'),['class'=>'form-control-label'], false ) }}
                                    {{ Form::text('userBankAccount[bank_iban]', old('userBankAccount[bank_iban]'), ['class' => 'form-control', 'placeholder' => __('message.bank_iban')]) }}
                                </div> -->

                                <div class="form-group col-md-6">
                                    {{ Form::label('bank_swift',__('message.bank_swift'),['class'=>'form-control-label'], false ) }}
                                    {{ Form::text('userBankAccount[bank_swift]', old('userBankAccount[bank_swift]'), ['class' => 'form-control', 'placeholder' => __('message.bank_swift')]) }}
                                </div>

                                <div class="form-group col-md-6">
                                    {{ Form::label('address',__('message.address'), ['class' => 'form-control-label']) }}
                                    {{ Form::textarea('address', null, ['class'=>"form-control textarea" , 'rows'=>3  , 'placeholder'=> __('message.address') ]) }}
                                </div>
                            </div>
                            <hr>
                            {{ Form::button('<span id="button-loader" style="display:none;"><div class="spinner-border spinner-border-sm text-light" role="status"></div></span> ' . __('message.save'), [
                                'type' => 'submit',
                                'class' => 'btn border-radius-10 btn-success float-right',
                                'id' => ''
                            ]) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {!! Form::close() !!}
    </div>
</x-master-layout>
