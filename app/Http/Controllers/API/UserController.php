<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\DriverDocument;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\DriverResource;
use Illuminate\Support\Facades\Password;
use App\Models\AppSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\DriverRequest;
use App\Models\Coupon;
use App\Notifications\CommonNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Http\Requests\DriverStepOneRequest;
use App\Models\UserAddress;
use App\Http\Resources\UserAddressResource;
use App\Models\RideRequest;

class UserController extends Controller
{
    public function register(UserRequest $request)
    {
        $input = $request->all();
        // Validate device ID if provided
        // Check referral code if provided
        $referrer = null;
        if (isset($input['referral_code'])) {
            $referrer = validateReferralCode($input['referral_code']);
            if (!$referrer) {
                return json_message_response(__('message.invalid_referral_code'), 422);
            }

            // Check device ID if referral code exists
            $validReferral = true;
            $unique_device_id_allow = SettingData('referral', 'unique_device_id') ?? null;
            if (isset($input['device_id']) && $unique_device_id_allow == 1) {
                if (!validateDeviceId($input['device_id'])) {
                    $validReferral = false;
                }
            }
        }
        
        $input['user_type'] = isset($input['user_type']) ? $input['user_type'] : 'rider';
        $input['password'] = Hash::make($input['contact_number']);
        $input['login_type'] = 'mobile';
        $input['referral_code'] = generateUniqueReferralCode();
        $input['referred_by'] = $referrer?->id;
        $input['device_id'] = $request->device_id ?? null;
        $input['device_type'] = $request->device_type ?? null;

        if( in_array($input['user_type'],['driver']))
        {
            $input['status'] = isset($input['status']) ? $input['status']: 'pending';
        }

        $input['display_name'] = $input['first_name']." ".$input['last_name'];
        $input['last_actived_at'] = now();
        $input['contact_number'] = trim($input['country_code']) . trim($input['contact_number']);
        
        if(isset($input['player_id'])) {
            $input['player_id'] = $input['player_id'];
        }

        // Create a customer in Stripe using the helper function
        $stripeCustomer = createStripeCustomer($input['email'], $input['display_name'], $input['contact_number'], 'stripe');
        if(isset($stripeCustomer['id'])) {
            $input['stripe_customer_id'] = $stripeCustomer['id'];
        } else {
            return json_message_response('Failed to create Stripe customer.',400);
        }

        $user = User::create($input);
        $user->assignRole($input['user_type']);

        if( $request->has('user_detail') && $request->user_detail != null ) {
            $user->userDetail()->create($request->user_detail);
        }

        // store rider address
        if( $request->has('user_address') && $request->user_address != null ) {
            $user->userAddresses()->create($request->user_address);
        }

        // Create referral record 
        if ($referrer) {
            createReferral($user->referred_by, $user->id, $validReferral);
            $referral_condition = SettingData('referral', 'referral_reward_condition') ?? null;
            if(isset($referral_condition) && $referral_condition == "on_registration"){
                // award referral bonus if applicable
                processReferral($user->referred_by, $user->id);
            }            
        }

        $message = __('message.save_form',['form' => __('message.'.$input['user_type']) ]);
        $user->api_token = $user->createToken('auth_token')->plainTextToken;
        $user->profile_image = getSingleMedia($user, 'profile_image', null);
        // send notification to rider when sign up
        $coupon = Coupon::where(['coupon_type' => 'new_user', 'status' => 1])->first();        
        if (!empty($coupon)) {
            $user_notification_data = [
                'id' => $user->id,
                'type' => 'new_user_benefits',
                'data' => $coupon->code,
                'message' => 'Congratulations! You have received a welcome coupon as a sign-up benefit.',
                'subject' => 'Welcome Bonus - Coupon for You',
            ];
            $user->notify(new CommonNotification($user_notification_data['type'], $user_notification_data));
        }
        $response = [
            'message' => $message,
            'data' => $user
        ];
        return json_custom_response($response);
    }

    public function driverRegister(DriverRequest $request)
    {
        $input = $request->all();
        $password = $input['password'];
        $input['user_type'] = isset($input['user_type']) ? $input['user_type'] : 'driver';
        $input['password'] = Hash::make($password);

        $input['status'] = isset($input['status']) ? $input['status']: 'pending';

        $input['display_name'] = $input['first_name']." ".$input['last_name'];
        $input['is_available'] = 1;
        $input['last_actived_at'] = now();
        if(isset($input['login_type']) && $input['login_type'] === 'mobile'){
            $input['contact_number'] =  trim($input['contact_number']);
        }else{
            $input['contact_number'] = trim($input['country_code']) . trim($input['contact_number']);
        }        
        $user = User::create($input);
        $user->assignRole($input['user_type']);

        if( $request->has('user_detail') && $request->user_detail != null ) {
            $user->userDetail()->create($request->user_detail);
        }
        
        if( $request->has('user_bank_account') && $request->user_bank_account != null ) {
            $user->userBankAccount()->create($request->user_bank_account);
        }

        if( $request->has('user_address') && $request->user_address != null ) {
            $user->userAddresses()->create($request->user_address);
        }

        if (!empty($request->profile_image)) {
            $base64Str = $request->profile_image;
        
            // Check if it has a base64 prefix
            if (preg_match('/^data:(.*?);base64,(.*)$/', $base64Str, $matches)) {
                $mimeType = $matches[1];
                $base64Data = base64_decode($matches[2]);
            } else {
                // Handle raw base64 without prefix (assume JPEG)
                $mimeType = 'image/jpeg';
                $base64Data = base64_decode($base64Str);
            }
        
            // Get file extension and temp path
            $extension = explode('/', $mimeType)[1] ?? 'jpg';
            $fileName = 'profile_image_' . Str::random(10) . '.' . $extension;
            $tempFilePath = storage_path('app/' . $fileName);
        
            // Save to temporary file
            File::put($tempFilePath, $base64Data);
        
            // Replace profile image
            $user->clearMediaCollection('profile_image');
            $user->addMedia($tempFilePath)
                 ->usingFileName($fileName)
                 ->toMediaCollection('profile_image');
        
            // Delete temp file
            File::delete($tempFilePath);
        }
        
        $user->userWallet()->create(['total_amount' => 0 ]);

        $message = __('message.save_form',['form' => __('message.driver') ]);
        $user->api_token = $user->createToken('auth_token')->plainTextToken;
        $user->is_verified_driver = (int) $user->is_verified_driver;// DriverDocument::verifyDriverDocument($user->id);
        $user->profile_image = getSingleMedia($user, 'profile_image', null);
        $response = [
            'message' => $message,
            'data' => $user
        ];
        return json_custom_response($response);
    }

    public function login(Request $request)
    {     
        Log::channel('custom_api')->info('[LOGIN] API called', ['request' => $request->all(),'line' => __LINE__]); 
        try {

            if(Auth::attempt(['email' => request('email'), 'password' => request('password'), 'user_type' => request('user_type')])){
                Log::channel('custom_api')->info('[LOGIN] Authentication successful', ['email' => $request->email,'line' => __LINE__]);
                $user = Auth::user();

                if( $user->status == 'banned' ) {
                    $message = __('message.account_banned');
                    Log::channel('custom_api')->warning('[LOGIN] Account is banned', ['email' => $request->email,'line' => __LINE__]);
                    return json_message_response($message,400);
                }

                if(request('player_id') != null){
                    $user->player_id = request('player_id');
                    // store player_id in firestore
                    $firestore = app('firebase.firestore');
                    if($user->uid != null){
                        $collection = $firestore->database()->collection('users')->document($user->uid);
                        $collection = $collection->update([['path' => 'player_id', 'value' => request('player_id')]]);
                    }
                }

                if(request('fcm_token') != null){
                    $user->fcm_token = request('fcm_token');
                }
                $user->last_actived_at = now();
                $user->save();
                $user->tokens()->delete();
                $success = $user;
                $success['api_token'] = $user->createToken('auth_token')->plainTextToken;
                $success['profile_image'] = getSingleMedia($user,'profile_image',null);
                $is_verified_driver = false;
                if($user->user_type == 'driver') {
                    $is_verified_driver = $user->is_verified_driver; // DriverDocument::verifyDriverDocument($user->id);
                }
                $success['is_verified_driver'] = (int) $is_verified_driver;
                unset($success['media']);
                Log::channel('custom_api')->info('[LOGIN] Login successful, response returned', ['email' => $request->email,'line' => __LINE__]);
                return json_custom_response([ 'data' => $success ], 200 );
            }
            else{
                Log::channel('custom_api')->warning('[LOGIN] Authentication failed', ['email' => $request->email,'line' => __LINE__]);
                $message = __('auth.failed');
                
                return json_message_response($message,400);
            }
        } catch (\Exception $e) {
            Log::channel('custom_api')->error('[LOGIN] Exception occurred', ['email'   => $request->email ?? null,'error'   => $e->getMessage(),'line' => __LINE__]);
            $message = __('auth.failed');
            return json_message_response($message,400);
        }
    }

    public function userList(Request $request)
    {
        $user_type = isset($request['user_type']) ? $request['user_type'] : 'rider';
        
        $user_list = User::query();
        
        $user_list->when(request('user_type'), function ($q) use($user_type) {
            return $q->where('user_type', $user_type);
        });

        $user_list->when(request('fleet_id'), function ($q) {
            return $q->where('fleet_id', request('fleet_id'));
        });

        if( $request->has('is_online') && isset($request->is_online) )
        {
            $user_list = $user_list->where('is_online',request('is_online'));
        }
        
        if( $request->has('status') && isset($request->status) )
        {
            $user_list = $user_list->where('status',request('status'));
        }

        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page))
        {
            if(is_numeric($request->per_page)){
                $per_page = $request->per_page;
            }
            if($request->per_page == -1 ){
                $per_page = $user_list->count();
            }
        }
        
        $user_list = $user_list->paginate($per_page);

        if( $user_type == 'driver' ) {
            $items = DriverResource::collection($user_list);
        } else {
            $items = UserResource::collection($user_list);
        }

        $response = [
            'pagination' => json_pagination_response($items),
            'data' => $items,
        ];
        
        return json_custom_response($response);
    }

    public function userDetail(Request $request)
    {
        $id = $request->id;

        $user = User::where('id',$id)->first();
        if(empty($user))
        {
            $message = __('message.user_not_found');
            return json_message_response($message,400);   
        }

        $response = [
            'data' => null,
        ];
        if( $user->user_type == 'driver') {
            $user_detail = new DriverResource($user);

            $response = [
                'data' => $user_detail,
                'required_document' => driver_required_document($user),
            ];
        } else {
            $user_detail = new UserResource($user);
            $response = [
                'data' => $user_detail
            ];
        }

        return json_custom_response($response);

    }

    public function changePassword(Request $request){
        $user = User::where('id',Auth::user()->id)->first();

        if($user == "") {
            $message = __('message.user_not_found');
            return json_message_response($message,400);   
        }
           
        $hashedPassword = $user->password;

        $match = Hash::check($request->old_password, $hashedPassword);

        $same_exits = Hash::check($request->new_password, $hashedPassword);
        if ($match)
        {
            if($same_exits){
                $message = __('message.old_new_pass_same');
                return json_message_response($message,400);
            }

			$user->fill([
                'password' => Hash::make($request->new_password)
            ])->save();
            
            $message = __('message.password_change');
            return json_message_response($message,200);
        }
        else
        {
            $message = __('message.valid_password');
            return json_message_response($message,400);
        }
    }

    public function updateProfile(UserRequest $request)
    {   
        $user = Auth::user();
        if($request->has('id') && !empty($request->id)){
            $user = User::where('id',$request->id)->first();
        }
        if($user == null){
            return json_message_response(__('message.no_record_found'),400);
        }
        // Check if email, name, or phone number has changed
        $emailChanged = $user->email !== $request->email;
        $nameChanged = $user->display_name !== $request->first_name . ' ' . $request->last_name;
        $phoneChanged = $user->contact_number !== $request->contact_number;

        if ($emailChanged || $nameChanged || $phoneChanged) {
            // Update Stripe customer
            if ($user->stripe_customer_id) {
                $stripeResponse = updateStripeCustomer($user->stripe_customer_id, $request->email, $request->first_name . ' ' . $request->last_name, $request->contact_number);
                if (isset($stripeResponse['error'])) {
                    return json_message_response('Failed to update Stripe customer.',400);
                }
            }
        }

        $user->fill($request->all())->update();

        // fixed image upload issue
        if($request->hasFile('profile_image')) {
            $user->clearMediaCollection('profile_image');
            $user->addMediaFromRequest('profile_image')->toMediaCollection('profile_image');
        }

        $user_data = User::find($user->id);
        
        if($user_data->userDetail != null && $request->has('user_detail') ) {
            $user_data->userDetail->fill($request->user_detail)->update();
        } else if( $request->has('user_detail') && $request->user_detail != null ) {
            $user_data->userDetail()->create($request->user_detail);
        }
        
        if($user_data->userBankAccount != null && $request->has('user_bank_account')) {
            $user_data->userBankAccount->fill($request->user_bank_account)->update();
        } else if( $request->has('user_bank_account') && $request->user_bank_account != null ) {
            $user_data->userBankAccount()->create($request->user_bank_account);
        }

        // Update or create addresses
        if ($request->filled('user_address')) {

            $addresses = is_array($request->user_address)
                ? $request->user_address
                : json_decode($request->user_address, true);

            foreach ($addresses as $addressData) {
                // Normalize label
                $label = $addressData['label'] ?? null;

                // Validate custom label presence
                if ($label === 'custom' && empty($addressData['custom_label'])) {
                    continue; // or throw validation error
                }

                // HOME / WORK / OTHER → single record
                if (in_array($label, ['home', 'work', 'other'])) {
                    $user->userAddresses()->updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'label' => $label
                        ],
                        $addressData
                    );
                }
                // CUSTOM → multiple allowed
                else if ($label === 'custom') {
                    $user->userAddresses()->updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'label' => 'custom',
                            'custom_label' => $addressData['custom_label'],
                        ],
                        $addressData
                    );
                }
            }
        }
        
        $message = __('message.updated');
        // $user_data['profile_image'] = getSingleMedia($user_data,'profile_image',null);
        unset($user_data['media']);

        if( $user_data->user_type == 'driver') {
            $user_resource = new DriverResource($user_data);
        } else {
            $user_resource = new UserResource($user_data);
        }

        $response = [
            'data' => $user_resource,
            'message' => $message
        ];
        return json_custom_response( $response );
    }

    public function logout(Request $request)
    {
        $user = Auth::user();

        if($request->is('api*')){
            $clear = request('clear');
            if( $clear != null ) {
                $user->$clear = null;
            }
            // Revoke the current access token
            $request->user()->currentAccessToken()->delete();
            $user->save();
            return json_message_response('Logout successfully');
        }
    }

    public function forgetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $response = Password::sendResetLink(
            $request->only('email')
        );

        return $response == Password::RESET_LINK_SENT
            ? response()->json(['message' => __($response), 'status' => true], 200)
            : response()->json(['message' => __($response), 'status' => false], 400);
    }
    
    public function socialLogin(Request $request)
    {
        $input = $request->all();
        Log::channel('custom_api')->info('[SOCIAL_LOGIN] API called', ['request' => $input,'line' => __LINE__]);

        try{
            if($input['login_type'] === 'mobile'){
                $user_data = User::where('contact_number', $input['contact_number'])->where('login_type','mobile')->first();
            } else {
                $user_data = User::where('email',$input['email'])->first();
            }
            
            if( $user_data != null ) {
                if( !in_array($user_data->user_type, ['admin',request('user_type')] )) {
                    $message = __('auth.failed');
                    Log::channel('custom_api')->info('[SOCIAL_LOGIN] Auth failed due to user type mismatch', ['email' => $user_data->email,'line' => __LINE__]);
                    return json_message_response($message,400);
                }
    
                if( $user_data->status == 'banned' ) {
                    $message = __('message.account_banned');
                    Log::channel('custom_api')->info('[SOCIAL_LOGIN] Account is banned', ['email' => $user_data->email,'line' => __LINE__]);
                    return json_message_response($message,400);
                }
            
                if( !isset($user_data->login_type) || $user_data->login_type  == '' )
                {
                    if($request->login_type === 'google')
                    {
                        $message = __('validation.unique',['attribute' => 'email' ]);
                    } else {
                        $message = __('validation.unique',['attribute' => 'contact_number' ]);
                    }
                    Log::channel('custom_api')->info('[SOCIAL_LOGIN] Email/Username validation error', [
                        'email' => $user_data->email,
                        'message' => $message,
                        'line' => __LINE__
                    ]);
                    return json_message_response($message,400);
                }
                $message = __('message.login_success');
            } else {
    
                if($request->login_type === 'google')
                {
                    $key = 'email';
                    $value = $request->email;
                } else {
                    $key = 'contact_number';
                    $value = $request->contact_number;
                }
    
                if($request->login_type === 'mobile' && $user_data == null ){
                    $otp_response = [
                        'status' => true,
                        'is_user_exist' => false
                    ];
                    Log::channel('custom_api')->info('[SOCIAL_LOGIN] Mobile login, user not found', ['username' => $input['username'], 'otp_response' => $otp_response,'line' => __LINE__]);
                    return json_custom_response($otp_response);
                }
                
                $validator = Validator::make($input,[
                    'contact_number' => 'required|max:20|unique:users,contact_number',
                ]);
    
                if ( $validator->fails() ) {
                    $data = [
                        'status' => false,
                        'message' => $validator->errors()->first(),
                        'all_message' =>  $validator->errors()
                    ];
                    Log::channel('custom_api')->info('[SOCIAL_LOGIN] Validation error during registration', ['input' => $input, 'errors' => $data,'line' => __LINE__]);
                    return json_custom_response($data, 422);
                }
    
                $password = !empty($input['accessToken']) ? $input['accessToken'] : $input['contact_number'];
    
                $input['display_name'] = $input['first_name']." ".$input['last_name'];
                $input['password'] = Hash::make($password);
                $input['user_type'] = isset($input['user_type']) ? $input['user_type'] : 'rider';

                $find_user = User::where('email',$input['email'])->first();
                if(empty($find_user)){
                    // Create a customer in Stripe using the helper function
                    $stripeCustomer = createStripeCustomer($input['email'], $input['display_name'], '', 'stripe');
                    if(isset($stripeCustomer['id'])) {
                        $input['stripe_customer_id'] = $stripeCustomer['id'];
                    } else {
                        return json_message_response('Failed to create Stripe customer.',400);
                    }
                } 

                $user = User::create($input);
                if($user->userWallet == null) {
                    $user->userWallet()->create(['total_amount' => 0 ]);
                }
                $user->assignRole($input['user_type']);
    
                $user_data = User::where('id',$user->id)->first();
                $message = __('message.save_form',['form' => $input['user_type'] ]);
            }
    
            $user_data['api_token'] = $user_data->createToken('auth_token')->plainTextToken;
            $user_data['profile_image'] = getSingleMedia($user_data, 'profile_image', null);
    
            $is_verified_driver = false;
            if($user_data->user_type == 'driver') {
                $is_verified_driver = $user_data->is_verified_driver;
            }
            $user_data['is_verified_driver'] = (int) $is_verified_driver;
            $response = [
                'status' => true,
                'message' => $message,
                'data' => $user_data
            ];

            Log::channel('custom_api')->info('[SOCIAL_LOGIN] Login successful, response returned', [
                'email' => $user_data->email ?? null,
                'response' => $response,
                'line' => __LINE__
            ]);
            return json_custom_response($response);
        } catch (\Exception $e) {
            Log::channel('custom_api')->error('[SOCIAL_LOGIN] Exception occurred', [
                'error' => $e->getMessage(),
                'line' => __LINE__
            ]);
            $message = __('auth.failed');
            return json_message_response($message,400);
        }
    }
    public function updateUserStatus(Request $request)
    {
        $user_id = $request->id ?? auth()->user()->id;
        
        $user = User::where('id',$user_id)->first();

        if($user == "") {
            $message = __('message.user_not_found');
            return json_message_response($message,400);
        }
        if($request->has('status')) {
            $user->status = $request->status;
        }
        if($request->has('is_online')) {
            $user->is_online = $request->is_online;
        }
        // if($request->has('is_available')) {
        //     $user->is_available = $request->is_available;
        // }
        if($request->has('latitude')) {
            $user->latitude = $request->latitude;
        }
        if($request->has('longitude')) {
            $user->longitude = $request->longitude;
        }
        if($request->has('latitude') && $request->has('longitude') ) {
            $user->last_location_update_at = date('Y-m-d H:i:s');            
        }
        if($request->has('player_id')) {
            $user->player_id = $request->player_id;
        }
        if($request->has('app_version')) {
            $user->app_version = $request->app_version;
        }

        if($request->has('otp_verify_at')) {
            $user->otp_verify_at = $request->otp_verify_at;
        }

        if($request->has('fcm_token')) {
            $user->fcm_token = $request->fcm_token;
        }
        
        if($request->is_online == 1) {
            $user->is_available = 1;
        }
        $user->last_actived_at = date('Y-m-d H:i:s');
        $user->save();
        /*
        if( $user->user_type == 'driver') {
            $user_resource = new DriverResource($user);
        } else {
            $user_resource = new UserResource($user);
        }*/
        $user_resource = null;
        $message = __('message.update_form',['form' => __('message.status') ]);
        $response = [
            'data' => $user_resource,
            'message' => $message
        ];
        return json_custom_response($response);
    }

    public function updateAppSetting(Request $request)
    {
        $data = $request->all();
        AppSetting::updateOrCreate(['id' => $request->id],$data);
        $message = __('message.save_form',['form' => __('message.app_setting') ]);
        $response = [
            'data' => AppSetting::first(),
            'message' => $message
        ];
        return json_custom_response($response);
    }

    public function getAppSetting(Request $request)
    {
        if($request->has('id') && isset($request->id)){
            $data = AppSetting::where('id',$request->id)->first();
        } else {
            $data = AppSetting::first();
        }

        return json_custom_response($data);
    }

    public function deleteUserAccount(Request $request)
    {
        $id = auth()->id();
        $user = User::where('id', $id)->first();
        $message = __('message.not_found_entry',['name' => __('message.account') ]);

        if( $user != '' ) {
            // Delete Stripe customer
            if ($user->stripe_customer_id) {
                $stripeResponse = deleteStripeCustomer($user->stripe_customer_id);
                if (isset($stripeResponse['error'])) {
                    return json_message_response('Failed to delete Stripe customer.',400);
                }
            }
            $user->delete();
            $message = __('message.account_deleted');
        }
        
        return json_custom_response(['message'=> $message, 'status' => true]);
    }

    public function validateDriverStepOne(DriverStepOneRequest $request)
    {
        return json_custom_response(['status' => true]);
    }

    public function riderRecentRideLocation(Request $request)
    {
        $user = auth()->user();

        $recent_rides = RideRequest::where('rider_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get();

        if ($recent_rides->isEmpty()) {
            return json_custom_response([
                'message' => 'No recent rides found',
                'data' => null
            ]);
        }

        $response = $recent_rides->map(function ($ride) {
            return [
                'address' => $ride->destination_address,
                'latitude' => $ride->destination_latitude,
                'longitude' => $ride->destination_longitude
            ];
        })->unique('address')->values();

        return json_custom_response([
            'data' => $response
        ]);
    }

    public function getUserAddressList(Request $request)
    {
        $user = auth()->user();

        $user_address = UserAddress::query();
        $user_address = $user_address->where('user_id', $user->id);

        $per_page = config('constant.PER_PAGE_LIMIT');
        if ($request->has('per_page') && !empty($request->per_page)) {
            if (is_numeric($request->per_page)) {
                $per_page = $request->per_page;
            }
            if ($request->per_page == -1) {
                $per_page = $user_address->count();
            }
        }

        $user_address = $user_address->orderBy('id', 'asc')->paginate($per_page);
        $items = UserAddressResource::collection($user_address);

        $response = [
            'pagination' => json_pagination_response($items),
            'data' => $items,
        ];

        return json_custom_response($response);
    }
}
