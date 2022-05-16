<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

use App\Models\OrderUser;
use App\Imports\UsersImport;
use Maatwebsite\Excel\Facades\Excel;

use Illuminate\Http\Request;
use Session, Image, File, Hashids, DataTables;


class OrderUserController extends Controller
{
    
    public $resource = 'admin/order-users';
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        if($request->ajax()){
            $customers = OrderUser::query();

            return Datatables::of($customers)
                ->addColumn('name', function ($customer) {
                    return $customer->first_name.' '.$customer->last_name;
                })
                ->editColumn('id', 'ID: {{$id}}')
                ->rawColumns(['action'])
                ->make(true);

        }
        return view('admin.order-users.index');
    }
    
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function import()
    {               
        return view($this->resource.'/create');                
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function importUsers(Request $request)
    {
        $this->validate($request, [
            'file' => 'required',
        ]);
        
        Excel::import(new UsersImport,$request->file('file'));
        
        Session::flash('success', 'Users successfully imported');
        return redirect()->route('admin.home');
    }

}