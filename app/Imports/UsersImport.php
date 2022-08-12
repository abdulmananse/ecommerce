<?php

namespace App\Imports;

use App\Models\User;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use DB;
use Hash;

class UsersImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        if (empty($row[0]) || $row[0] == 'NO') {
            return null;
        }
        
            
        $contactNo = str_replace('+', '', $row[5]);
        $contactNo = str_replace(' ', '', $contactNo);

        $email = strtolower($row[4]);
        if (empty($row[4])) {
            $email = str_replace(' ', '.', strtolower($row[2])).'@thesupervan.co.uk';
        }

        
        $userData = [
            'customer_id'         =>  $row[1],
            'name'         =>  $row[2],
            'email'        =>  $email,
            'password'        => Hash::make($email),
            'company_name'         =>  $row[3],
            'shop_name'         =>  $row[3],
            'contact_no'  =>  $contactNo,
            'phone'  =>  $contactNo,
            'address'     =>  $row[6],
            //'town'     =>  $row[6],
            //'city'     =>  $row[5],
            //'postal_code'     =>  $row[6],
            'notes'     =>  $row[7],
            'type' => 'shopkeeper'
        ];

        $user = User::updateOrCreate(
            ['phone' => $contactNo, 'email' => $email],
            $userData
        );
        
        return $user;
        
        return null;
    }
}
