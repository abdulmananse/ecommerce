<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'paypal_id',
        'payment_method',
        'cart_id',
        'admin_id',
        'parent_id',
        'qty',
        'cost',
        'cost_of_goods',
        'discount',
        'tax',
        'amount',
        'cart_details',
        'trans_details',
        'type',
        'updated_columns',
        'is_latest'
    ];

    public function user(){
        return $this->belongsTo(\App\User::class);
    }

    public function cart(){
        return $this->belongsTo(ShoppingCart::class,'cart_id');
    }

    public function purchasedItems(){
        return $this->hasMany(ShoppingCartHistory::class,'transaction_id');
    }

    public function product(){
        return $this->belongsTo(Product::class,'product_id');
    }
    
    public function scopeDateFilter($query){
       
        return $query->whereDate('created_at','>=',request()->from_date)->whereDate('created_at','<=',request()->to_date);
    }
}
