<?php

namespace App\Imports;

use App\Models\OrderUser;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use DB;

class UsersImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        if (empty($row[1])) {
            return null;
        }
        
        if ($row[0] != 'Shop Name') {
            
            $contactNo = str_replace('+', '', $row[4]);
            $contactNo = str_replace(' ', '', $contactNo);
            
             $userData = [
                'first_name'         =>  $row[2],
                'last_name'        =>  $row[3],
                'owner_name'       =>  $row[1],
                'shop_name'         =>  $row[0],
                'contact_no'  =>  $contactNo,
                'address'     =>  $row[5],
                'town'     =>  $row[6],
                'city'     =>  $row[7],
                'postal_code'     =>  $row[8],
                'notes'     =>  $row[9],
            ];

            $user = OrderUser::updateOrCreate(
                ['contact_no' => $contactNo],
                $userData
            );
            
            return $user;
        }
        
        return null;
    }
}
