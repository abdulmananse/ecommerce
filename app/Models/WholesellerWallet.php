<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WholesellerWallet extends Model
{
    protected $table = 'wholeseller_wallets';

    /**
     * The database primary key value.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    protected $guarded = ['id'];
    
     public function user()
    {
        return $this->belongsToMany(Models\User::class,'user_id');
    }
}
