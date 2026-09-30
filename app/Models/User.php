<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name', 'last_name', 'email', 'password', 'username','country_code', 'contact_number', 'gender', 'email_verified_at', 'address', 'user_type', 'player_id', 'fcm_token', 'fleet_id', 'latitude', 'longitude', 'last_notification_seen', 'status', 'is_online', 'is_available', 'uid', 'login_type', 'display_name', 'timezone', 'service_id', 'is_verified_driver', 'last_location_update_at', 'otp_verify_at','last_actived_at','app_version', 'referral_code', 'referred_by', 'device_id', 'device_type', 'date_of_birth', 'license_number', 'license_expiration_date', 'social_security_number','stripe_customer_id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_available'  => 'integer',
        'service_id'        => 'integer',
        'fleet_id'          => 'integer',
        'is_verified_driver'=> 'integer',
        'is_online'         => 'integer',
        'last_location_update_at'   => 'datetime',
        'otp_verify_at'     => 'datetime',
    ];

    public function userDetail() {
        return $this->hasOne(UserDetail::class, 'user_id', 'id');
    }

    public function userBankAccount() {
        return $this->hasOne(UserBankAccount::class, 'user_id', 'id');
    }

    public function fleet() {
        return $this->belongsTo(User::class, 'fleet_id', 'id');
    }

    public function userWallet() {
        return $this->hasOne(Wallet::class, 'user_id', 'id');
    }

    public function userPoint(){
        return $this->hasOne(Point::class, 'user_id', 'id');
    }

    public function userAddresses()
    {
        return $this->hasMany(UserAddress::class, 'user_id', 'id');
    }

    public function scopeAdmin($query) {
        return $query->where('user_type', 'admin')->first();
    }

    public function scopeGetUser($query, $user_type=null)
    {
        $auth_user = auth()->user();

        if( $auth_user->hasAnyRole(getActiveAdminsRoles()) ) {
            $query->where('user_type', $user_type)->where('status','active');
            return $query;
        }
        if( $auth_user->hasRole('fleet') ) {
            return $query->where('user_type', 'driver')->where('fleet_id', $auth_user->id);
        }
    }

    public function riderRideRequestDetail() {
        return $this->hasMany(RideRequest::class, 'rider_id', 'id');
    }

    public function driverRideRequestDetail() {
        return $this->hasMany(RideRequest::class, 'driver_id', 'id');
    }

    public function driverDocument(){
        return $this->hasMany(DriverDocument::class, 'driver_id', 'id');
    }

    public function service() {
        return $this->belongsTo(Service::class, 'service_id', 'id');
    }

    public function driverServices() {
        return $this->hasMany(DriverService::class, 'driver_id', 'id');
    }

    /** Services the admin has approved for this driver. */
    public function approvedDriverServices() {
        return $this->driverServices()->where('status', 1);
    }

    /**
     * Drivers eligible for a ride's service: service is admin-approved AND currently active for the driver.
     */
    public function scopeEligibleForService($query, $service_id) {
        return $query->whereHas('driverServices', function ($q) use ($service_id) {
            $q->where('service_id', $service_id)->where('status', 1)->where('is_active', 1);
        });
    }

    /**
     * Admin review of a driver's services. driver_services.status: 0 = requested (pending), 1 = approved, 2 = rejected/unapproved.
     * Rows are never deleted by review, so admin (and the driver) can still see rejected/unapproved services and re-approve them.
     * $actions: [service_id => 'approve'|'reject'|'remove'] applied to the driver's existing rows
     *           (approve: any non-approved row -> approved & active; reject/remove: row -> rejected, inactive; else no change).
     * $add:     service ids to add as approved (an existing pending/rejected row for the same service is approved).
     * users.service_id is kept pointing at an approved service (or null).
     */
    public function applyServiceDecisions(array $actions, array $add = []) {
        foreach ($this->driverServices()->get() as $row) {
            $action = $actions[$row->service_id] ?? null;
            if ($action === 'approve' && $row->status != 1) {
                $row->update(['status' => 1, 'is_active' => 1]);
            } elseif (in_array($action, ['reject', 'remove'], true) && $row->status != 2) {
                $row->update(['status' => 2, 'is_active' => 0]);
            }
        }

        foreach (Service::whereIn('id', array_filter($add))->pluck('id') as $service_id) {
            $row = DriverService::where('driver_id', $this->id)->where('service_id', $service_id)->first();
            if (!$row) {
                DriverService::create(['driver_id' => $this->id, 'service_id' => $service_id, 'status' => 1, 'is_active' => 1]);
            } elseif ($row->status != 1) {
                $row->update(['status' => 1, 'is_active' => 1]);
            }
        }

        $approved = $this->approvedDriverServices()->pluck('service_id')->all();
        $primary = in_array($this->service_id, $approved) ? $this->service_id : ($approved[0] ?? null);
        if ($this->service_id != $primary) {
            $this->forceFill(['service_id' => $primary])->save();
        }

        // A driver with approved services always keeps at least one active (e.g. admin removed the only active one).
        if ($primary !== null && !$this->approvedDriverServices()->where('is_active', 1)->exists()) {
            $this->approvedDriverServices()->where('service_id', $primary)->update(['is_active' => 1]);
        }
    }

    public function hasActiveService() {
        return $this->approvedDriverServices()->where('is_active', 1)->exists();
    }

    /**
     * Driver action: choose which ADMIN-APPROVED services are active. Never adds/removes approvals.
     * Returns false (and changes nothing) if any id is not approved for this driver.
     */
    public function setActiveServices(array $service_ids) {
        $service_ids = array_values(array_unique(array_map('intval', $service_ids)));
        $approved = $this->approvedDriverServices()->pluck('service_id')->map(function ($id) { return (int) $id; })->all();

        if (empty($service_ids) || count(array_diff($service_ids, $approved)) > 0) {
            return false;
        }
        $this->approvedDriverServices()->whereIn('service_id', $service_ids)->update(['is_active' => 1]);
        $this->approvedDriverServices()->whereNotIn('service_id', $service_ids)->update(['is_active' => 0]);
        return true;
    }

    public function riderRating(){
        return $this->hasMany(RideRequestRating::class, 'rider_id', 'id');
    }

    public function driverRating(){
        return $this->hasMany(RideRequestRating::class, 'driver_id', 'id');
    }

    public function routeNotificationForOneSignal()
    {
        return $this->player_id;
    }

    public function routeNotificationForFcm($notification)
    {
        return $this->fcm_token;
    }

    public function userWithdraw(){
        return $this->hasMany(WithdrawRequest::class, 'user_id', 'id');
    }

    public function bids()
    {
        return $this->hasMany(RideRequestBid::class, 'driver_id');
    }
    protected static function boot(){
        parent::boot();
        static::deleted(function ($row) {
            $row->userDetail()->delete();
            $row->userWithdraw()->delete();
            $row->userWallet()->delete();
            switch ($row->user_type) {
                case 'rider':
                    $row->riderRideRequestDetail()->delete();
                    break;
                case 'driver':
                    $row->userBankAccount()->delete();
                    $row->driverDocument()->delete();
                    $row->driverRideRequestDetail()->delete();
                    break;
                default:
                    # code...
                    break;
            }
        });
    }

    public function getPayment(){
        
        return $this->hasManyThrough( 
            Payment::class,
            RideRequest::class,
            'driver_id',
            'ride_request_id',
            'id',
            'id'
        )->where('payment_status','paid');
    }

    public function referredBy()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }
    
    public function getReferralCount($status = 'complete')
    {
        return $this->referrals()
            ->when($status, function ($query) use ($status) {
                return $query->where('status', $status);
            })
            ->count();
    }

    public function getDriverScoreAttribute()
    {
        $driverId = $this->id;

        $data = DB::table('ride_requests')
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN cancel_by = 'driver' THEN 1 ELSE 0 END) as cancelled,
                    (
                        SELECT COUNT(*) 
                        FROM ride_requests AS r2 
                        WHERE JSON_CONTAINS(r2.cancelled_driver_ids, ?)
                    ) as unaccepted
                ", ["$driverId"])
                ->where('driver_id', $driverId)
                ->first();

        $total = $data->total ?? 0;
        $cancelled = $data->cancelled ?? 0;
        $unaccepted = $data->unaccepted ?? 0;

        if ($total === 0) {
            return 100; // full score if no rides yet
        }

        // Scoring formula: 100 - % of failed rides
        $score = 100 - (($cancelled + $unaccepted) / $total * 100);
        return round($score, 2);
    }


    public function completedTripsAsRiderCount()
    {
        return RideRequest::where('rider_id', $this->id)
                        ->where('status', 'completed')
                        ->count();
    }

    public function completedTripsAsDriverCount()
    {
        return RideRequest::where('driver_id', $this->id)
                        ->where('status', 'completed')
                        ->count();
    }
}
