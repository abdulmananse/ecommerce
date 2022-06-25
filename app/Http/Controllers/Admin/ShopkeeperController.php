<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\WholesellerWallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

use App\User;
use Illuminate\Http\Request;
use Session,Hashids,DataTables,DB;

class ShopkeeperController extends Controller
{

   public function __construct()
   {
        $this->middleware('permission:view shopkeepers', ['only' => ['index']]);
        $this->middleware('permission:add shopkeepers', ['only' => ['create','store']]);
        $this->middleware('permission:edit shopkeepers', ['only' => ['edit','update']]);
        $this->middleware('permission:delete shopkeepers', ['only' => ['destroy']]);
    }

   /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {

        if($request->ajax()){

            $users = User::where('type','shopkeeper')->orderBy('updated_at','desc');

            return DataTables::of($users)
                ->addColumn('is_active', function ($user) {
                 $satatus = '<a class="btn btn-xs btn-danger">Inactive</a>';
                 if($user->is_active=="yes")
                    $satatus = '<a class="btn btn-xs btn-success">Active</a>';

                    return $satatus;
                })
                ->addColumn('name', function ($user) {
                  return $user->full_name;
                })
                ->addColumn('wallet_amount', function ($user) {
                    return number_format(getWholsellerDataWallet($user->id),'2','.','');
                })
                ->addColumn('action', function ($user) {
                $action = '';
                if(Auth::user()->can('edit shopkeepers'))
                    $action .= '<a href="customers/'. Hashids::encode($user->id).'/edit" class="text-primary" data-toggle="tooltip" title="Edit Shopkeeper"><i class="fa fa-lg fa-edit"></i></a>';
                if(Auth::user()->can('delete shopkeepers'))
                    $action .= '<a href="customers/'.Hashids::encode($user->id).'" class="text-danger btn-delete" data-toggle="tooltip" title="Delete Shopkeeper"><i class="fa fa-lg fa-trash"></i></a>';

                   $action .= '<a href="javascript:void(0)" class="text-primary walletAdd"  data-id="'.$user->id.'" data-toggle="tooltip" title="Add Wallet Amount"><i class="fa fa-google-wallet"></i></a>';

                return $action;
                })
                ->editColumn('id', 'ID: {{$id}}')
                ->rawColumns(['is_active','action'])
                ->make(true);

        }

        return view('admin.shopkeepers.index');
    }
}
