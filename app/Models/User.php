<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;


    /**
     * belongs To relation Store_products
     */

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'user_id');
    }

    public function getFullNameAttribute($value)
    {
        return $this->first_name.' '.$this->last_name;
    }
    
    public function getFullNameWithEmailAttribute($value)
    {
        return $this->first_name.' '.$this->last_name.' ('.$this->email.')';
    }
    
    public function getWholesalerNameAttribute($value)
    {
        $shopName = '';
        if (empty($this->owner_name)) {
            return $this->first_name.' '.$this->last_name;
        }
        if (!empty($this->shop_name)) {
            $shopName = ' (' . $this->shop_name . ')';
        }
        return $this->owner_name . $shopName;
    }
    
 public function getAmountSumAttribute(){
   
    return $this->transactions->sum('amount');
}


 public function scopeDropship($query)
    {
        return $query->whereType('dropshipper');
    }

    public function scopeWhole($query)
    {
        return $query->whereType('wholesaler');
    }
    
        public function shoppings()
    {
       return  $this->hasMany(ShoppingCart::class);
    }
    
      public function wallets()
    {
        return $this->hasMany(WholesellerWallet::class,'user_id');
    }
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name', 'last_name', 'email', 'password','phone','mobile', 'type','address',
        'owner_name', 'shop_name', 'town', 'city', 'postal_code', 'notes'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
