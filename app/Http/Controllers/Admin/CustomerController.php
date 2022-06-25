<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Hashids, DataTables;
use App\User;
use Session;

class CustomerController extends Controller
{


    public function __construct()
   {
        $this->middleware('permission:view customers', ['only' => ['index']]);
        $this->middleware('permission:add customers', ['only' => ['create','store']]);
        $this->middleware('permission:edit customers', ['only' => ['edit','update']]);
        $this->middleware('permission:delete customers', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        if($request->ajax()){
            $customers = User::where('type','retailer');

            return Datatables::of($customers)
                ->addColumn('name', function ($customer) {
                    return $customer->first_name.' '.$customer->last_name;
                })
                ->addColumn('profile_image', function ($customer) {
                    return '<img width="30" src="'.checkImage('customers/thumbs/'. $customer->profile_image).'" />';
                })
                ->addColumn('action', function ($order) {
                    return '<a href="orders?user_id='. Hashids::encode($order->id).'" target="_blank" class="btn btn-xs btn-warning">Orders</a>';
                })
                ->editColumn('id', 'ID: {{$id}}')
                ->rawColumns(['profile_image','action'])
                ->make(true);

        }
        return view('admin.customers.index');
    }
    
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('admin.customers.create');
    }
    
    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'first_name' => 'required|max:255',
            'last_name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|min:6'
        ]);

       $requestData = $request->all();
       $requestData['password'] = bcrypt($requestData['password']);

       $user = User::create($requestData);

        Session::flash('success', 'Shopkeeper added!');

        if ($request->type=='wholesaler') {
            return redirect('admin/wholesaler');
        }
        return redirect('admin/shopkeepers');
    }
    
    public function retailerOrders()
    {
        if(request()->ajax()){
            $customers = User::where('type','retailer')->orderBy('updated_at','desc');

            return Datatables::of($customers)
                ->addColumn('name', function ($customer) {
                    $name = $customer->first_name . ' ' . $customer->last_name;
                    $color ='black';
                    if($customer->is_latest){
                        $color='red';
                    }
                    return "<a style='color:$color'>$name</a>";
                })   ->addColumn('email', function ($customer) {
                    $name = $customer->email;
                    $color ='black';
                    if($customer->is_latest){
                        $color='red';
                    }
                    return "<a style='color:$color'>$name</a>";
                })
                ->addColumn('profile_image', function ($customer) {
                    return '<img width="30" src="'.checkImage('customers/thumbs/'. $customer->profile_image).'" />';
                })
                ->addColumn('action', function ($order) {
                    return '<a href="orders?user_id='. Hashids::encode($order->id).'" target="_blank" class="btn btn-xs btn-warning">Orders</a> | <a href="' . url('admin/update-status/retailer/'.Hashids::encode($order->id)). '"  class="btn btn-xs btn-success"><i class="fa fa-edit"></i></a>';
                })
                ->editColumn('id', 'ID: {{$id}}')
                ->rawColumns(['profile_image','action','name','email'])
                ->make(true);

        }
        return view('admin.customers.retailer_orders');
    }
}
