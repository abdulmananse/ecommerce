<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OrderUser;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

use App\Models\Transaction;
use App\Models\Courier;
use App\Models\VanStoreProduct;
use App\Models\ShoppingCart;
use App\Models\ShoppingCartHistory;
use App\Models\UserWallet;
use Illuminate\Http\Request;
use Session, Hashids, DataTables;
use App\Models\Email;
use App\Models\TaxRate;
use App\Models\Quotation;
use function foo\func;
use Carbon\Carbon;
use Log;

class AdminOrderController extends Controller
{

    public $resource = 'admin/admin-orders';

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {   
        
        if ($request->ajax()) {

            $id = $request->input('user_id');
            $orders = Transaction::with(['cart'])->where('type', 'admin_order')->dateFilter();
            
            $admin = Auth::user();
            if ($admin->hasRole(2)) {
                $orders =$orders->where('admin_id', $admin->id);
            }
            
            if ($id) {
                $user_id = decodeId($id);

                $orders =$orders->whereUserId($user_id);
            }

            if($request->filled('order')) {
                $orderBy = $request->order;
                if ($orderBy[0]['column'] == 0) {
                    $orders->orderBy('id', $orderBy[0]['dir']);
                }
                if ($orderBy[0]['column'] == 3) {
                    $orders->orderBy('amount', $orderBy[0]['dir']);
                }
                if ($orderBy[0]['column'] == 4) {
                    $orders->orderBy('cost', $orderBy[0]['dir']);
                }
                if ($orderBy[0]['column'] == 5) {
                    $orders->orderBy('tax', $orderBy[0]['dir']);
                }
            } else {
                $orders->orderBy('id', 'desc');
            }

            return Datatables::of($orders)
                ->addColumn('orders_details', function ($order) {
                    return '<span class="details-control"></span>';
                })
                ->addColumn('username', function ($order) {
                    $username = '';
                    if ($order->cart) {
                        $user = unserialize($order->cart->user_details);
                        $username = @$user["first_name"].' '.@$user["last_name"];
                        if (!empty(@$user['name'])) {
                            $username = $user['name'];
                        }
                    }
                    
                    return $username;
                })
                ->addColumn('orderId', function ($order) {
                    return '<u><a href="admin-orders/' . Hashids::encode($order->id) . '" class="text-success btn-order" data-toggle="tooltip" title="View Invoice">'. $order->id . '</a></u>';
                })
                ->addColumn('amount', function ($order) {
                    $color ='';
                    if($order['is_latest']){
                        $color='red';
                    }else{
                        $color ='black';
                    }
                    $amount = $order->amount > 0 ? numberTo2DecimelFloat($order->amount) : 0;
                    return "<a style='color: $color'>".'£'." $amount </a>";
                })
                ->addColumn('cost', function ($order) {
                    return numberTo2DecimelFloat($order->cost);
                })
                ->addColumn('tax', function ($order) {
                    return numberTo2DecimelFloat($order->tax);
                })
                ->addColumn('barcode_image', function ($order) {
                    $user =  User::where('id',$order->user_id)->first();

                    if($user and $order->label_image and $user->type !='wholesaler'){
                        return '<a href="'.url($order->label_image).'" download><img src="'.url($order->label_image).'" alt="'.$order->label_image.'" width="100" height="200"></a>';
                    }else{
                        return '';
                    }
                })
                ->addColumn('action', function ($order) {
                    
                    $action = '';

                    $action .='<a href="admin-orders/' . Hashids::encode($order->id) . '" class="text-success btn-order" data-toggle="tooltip" title="View Quotation"><i class="fa fa-eye fa-lg"></i></a>';
                    $action .='<a href="admin-orders/quotation/' . Hashids::encode($order->id) . '" class="text-primary btn-order" data-toggle="tooltip" title="Generate Invoice"><i class="fa fa-file fa-lg"></i></a>';
                    $action .='<a href="' .url('admin/admin-orders/'.Hashids::encode($order->id) ). '/edit" class="text-success btn-order" data-toggle="tooltip" title="Edit Quotation"><i class="fa fa-edit"></i></a>';
                    $action .= '<a href="admin-orders/'.Hashids::encode($order->id).'" class="text-danger btn-delete" data-toggle="tooltip" title="Delete Quotation"><i class="fa fa-lg fa-trash"></i></a>';
                    return $action;
                })
                ->rawColumns(['discounted_price','amount', 'orders_details', 'orderId', 'email', 'status', 'action','barcode_image','courier_service'])
                ->make(true);
        }

        return view($this->resource . '/index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $customers = User::whereIn('type', ['wholesaler','shopkeeper'])
        ->pluck('customer_id', 'id')->prepend('Select Customer', '');
        $products = Product::has('quantity')->pluck('name', 'id')->prepend('Select Product', '');
        
        return view($this->resource . '/create', get_defined_vars());
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
        $paymentMethod = 'none';
        if ($request->filled('payment_method')) {
            $paymentMethod = $request->payment_method;
        }
        $customerId = $request->customer_id;
        $customer = User::find($customerId);
        $admin = Auth::user();
        $cartData['user_id'] = $customerId;
        if ($customer) {
            $cartData['user_details'] = serialize($customer->toArray());
        }
        
        $productQuantity = $request->product_quantity;
        $productPrice = $request->product_price;
        $productData = [];
        $historyData = array();
        $tax        = 0;
        $cost       = 0;
        $tCost       = 0;
        $discount   = 0;
        $totalQty   = 0;
        $totalAmount   = 0;
        foreach($request->product_id as $key => $productId) {
            $product = Product::with('tax_rate')->find($productId);
            if ($product) {
                //$price = $product->price;
                
                $price = isset($productPrice[$key]) ? $productPrice[$key] : $product->price;
                
                $qty = isset($productQuantity[$key]) ? $productQuantity[$key] : 1;
                $taxRate = getVatCharges(); //$product->tax_rate
                if ($taxRate > 0) {
                    $tax = ($tax + (($taxRate / 100) * $price) * $qty);
                }
                $tCost = ($tCost + ($product->cost * $qty));
                $cost       = ($cost + ($price * $qty));
                $discount   = $discount + (($price - $product->discountedPrice) * $qty);
                $totalQty = $totalQty + $qty;
                
                $productData[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $price, //$product->discountedPrice,
                    'quantity' => $qty,
                ];
                
                $input['product_id']            = $product->id;
                $input['quantity']              = $qty;
                $input['amount_per_item']       = $price; //$product->discountedPrice;
                $input['created_at']            = Carbon::now();
                $input['updated_at']            = Carbon::now();
                array_push($historyData, $input);
            }
        }
        
        $cartData['cart_details'] = serialize($productData);
        $cartData['payment_status'] = 'pending';
        $cartData['delivery_status'] = 'pending';
        
        $shoppingCart = ShoppingCart::create($cartData);
        if ($shoppingCart) {
            
            //$totalAmount = ($cost - $discount) + $tax;
            $totalAmount = $cost;
            
            $transaction['user_id']   = $customerId;
            $transaction['cart_id']   = $shoppingCart->id;
            $transaction['qty']       = $totalQty;
            $transaction['cost']      = numberTo2DecimelFloat($cost);
            $transaction['cost_of_goods']      = numberTo2DecimelFloat($tCost);
            $transaction['discount']  = numberTo2DecimelFloat($discount);
            $transaction['tax']       = numberTo2DecimelFloat($tax);
            $transaction['paypal_id'] = 0;
            $transaction['payment_method'] = $paymentMethod;
            $transaction['amount']    = numberTo2DecimelFloat($totalAmount);
            $transaction['is_latest']  = 1;
            $transaction['trans_details']  = null;
            $transaction['type']  = 'admin_order';
            if ($admin->hasRole(2)) {
                $transaction['admin_id']  = $admin->id;
            }
            // create transaction
            $transaction = Transaction::create($transaction);
            
            if ($transaction) {
                $historyData = collect($historyData);
                $historyData = $historyData->map(function ($item) use ($transaction) {
                    $item['transaction_id'] = $transaction->id;
                    
                    $admin = Auth::user();
                    if ($admin->hasRole(1)) {
                        // remove product quantity from main store
                        updateProductStockByData($item['product_id'], 1, $item['quantity'], 2, 3, $transaction->id, $transaction->user_id, 'Quotation created by admin');
                    }
                    if ($admin->hasRole(2)) {
                        // remove product quantity to van store
                        updateVanStoreProductStockByData($item['product_id'], $item['quantity'], 2, 3, $transaction->id, $admin->id, 'Quotation created by van store');
                    }
                    
                    return $item;
                });
                ShoppingCartHistory::insert($historyData->toArray()); 
                
                
                if ($paymentMethod == '2pay') { 
                    UserWallet::create([
                        'debit' => $transaction->amount,
                        'user_id' => $customerId,
                        'order_id' => $transaction->id,
                    ]);
                }
                
            }
        }
        
        return response()->json([
            'success'  => true,
            'message'  => 'Quotation successfully created'
        ], $this->successStatus);
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {

        $id = decodeId($id);

        $order = Transaction::with(['cart', 'purchasedItems.product.product_images'])->find($id);

        $vatCharges = TaxRate::select('rate')->where('id',1)->first();
        $vatCharges = (int)$vatCharges->rate;


        return view($this->resource . '/invoice', compact('order','vatCharges'));
    }
    
    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     *
     * @return \Illuminate\View\View
     */
    public function edit($id)
    {
        $id = decodeId($id);
        $customers = User::whereIn('type', ['wholesaler','shopkeeper'])
        ->pluck('customer_id', 'id')->prepend('Select Customer', '');
        $products = Product::has('quantity')->pluck('name', 'id')->prepend('Select Product', '');
        $order = Transaction::with(['cart', 'purchasedItems.product'])->find($id);
        //dd($order->purchasedItems->toArray());
        return view($this->resource . '/edit', compact('order', 'customers', 'products'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int $id
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function update($id, Request $request)
    {
        $order = Transaction::find($id);

        $paymentMethod = 'none';
        if ($request->filled('payment_method')) {
            $paymentMethod = $request->payment_method;
        }
        $customerId = $request->customer_id;
        $customer = User::find($customerId);
        $admin = Auth::user();
        $cartData['user_id'] = $customerId;
        if ($customer) {
            $cartData['user_details'] = serialize($customer->toArray());
        }
        
        $productQuantity = $request->product_quantity;
        $productPrice = $request->product_price;
        $productData = [];
        $historyData = array();
        $tax        = 0;
        $cost       = 0;
        $tCost       = 0;
        $discount   = 0;
        $totalQty   = 0;
        $totalAmount   = 0;
        foreach($request->product_id as $key => $productId) {
            $product = Product::with('tax_rate')->find($productId);
            if ($product) {
                //$price = $product->price;
                
                $price = isset($productPrice[$key]) ? $productPrice[$key] : $product->price;
                
                $qty = isset($productQuantity[$key]) ? $productQuantity[$key] : 1;
                $taxRate = getVatCharges(); //$product->tax_rate
                if ($taxRate > 0) {
                    $tax = ($tax + (($taxRate / 100) * $price) * $qty);
                }
                $tCost = ($tCost + ($product->cost * $qty));
                $cost       = ($cost + ($price * $qty));
                $discount   = $discount + (($price - $product->discountedPrice) * $qty);
                $totalQty = $totalQty + $qty;
                
                $productData[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $price, //$product->discountedPrice,
                    'quantity' => $qty,
                ];
                
                $input['product_id']            = $product->id;
                $input['quantity']              = $qty;
                $input['amount_per_item']       = $price; //$product->discountedPrice;
                $input['created_at']            = Carbon::now();
                $input['updated_at']            = Carbon::now();
                array_push($historyData, $input);
            }
        }
        
        $cartData['cart_details'] = serialize($productData);
        $cartData['payment_status'] = 'pending';
        $cartData['delivery_status'] = 'pending';
        
        $shoppingCart = ShoppingCart::where('id', $order->cart_id)->update($cartData);
        if ($shoppingCart) {
            
            //$totalAmount = ($cost - $discount) + $tax;
            $totalAmount = $cost;
            
            $transaction['user_id']   = $customerId;
            $transaction['cart_id']   = $order->cart_id;
            $transaction['qty']       = $totalQty;
            $transaction['cost']      = numberTo2DecimelFloat($cost);
            $transaction['cost_of_goods']      = numberTo2DecimelFloat($tCost);
            $transaction['discount']  = numberTo2DecimelFloat($discount);
            $transaction['tax']       = numberTo2DecimelFloat($tax);
            $transaction['paypal_id'] = 0;
            $transaction['payment_method'] = $paymentMethod;
            $transaction['amount']    = numberTo2DecimelFloat($totalAmount);
            $transaction['is_latest']  = 1;
            $transaction['trans_details']  = null;
            $transaction['type']  = 'admin_order';
            if ($admin->hasRole(2)) {
                $transaction['admin_id']  = $admin->id;
            }
            // create transaction
            $transaction = Transaction::where('id', $order->id)->update($transaction);
            
            if ($transaction) {

                $schs = ShoppingCartHistory::where('transaction_id', $order->id)->get();
                foreach($schs as $sch){
                    $admin = Auth::user();
                    if ($admin->hasRole(1)) {
                        // remove product quantity from main store
                        updateProductStockByData($sch->product_id, 1, $sch->quantity, 1, 4, $order->id, $order->user_id, 'Quotation updated by admin');
                    }
                    if ($admin->hasRole(2)) {
                        // remove product quantity to van store
                        updateVanStoreProductStockByData($sch->product_id, $sch->quantity, 1, 4, $order->id, $admin->id, 'Quotation updated by van store');
                    }
                }

                $historyData = collect($historyData);
                $historyData = $historyData->map(function ($item) use ($order) {
                    $item['transaction_id'] = $order->id;
                    
                    $admin = Auth::user();
                    if ($admin->hasRole(1)) {
                        // remove product quantity from main store
                        updateProductStockByData($item['product_id'], 1, $item['quantity'], 2, 3, $order->id, $order->user_id, 'Quotation updated by admin');
                    }
                    if ($admin->hasRole(2)) {
                        // remove product quantity to van store
                        updateVanStoreProductStockByData($item['product_id'], $item['quantity'], 2, 3, $order->id, $admin->id, 'Quotation updated by van store');
                    }
                    
                    return $item;
                });
                ShoppingCartHistory::where('transaction_id', $order->id)->delete();
                ShoppingCartHistory::insert($historyData->toArray()); 
                
                
                if ($paymentMethod == '2pay') { 
                    UserWallet::create([
                        'debit' => $transaction->amount,
                        'user_id' => $customerId,
                        'order_id' => $transaction->id,
                    ]);
                }
                
            }
        }
        
        return response()->json([
            'success'  => true,
            'message'  => 'Quotation successfully updated'
        ], $this->successStatus);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function destroy($id)
    {
        $id = decodeId($id);
        $order = Transaction::find($id);
        if ($order) {
            ShoppingCart::where('id', $order->cart_id)->delete();

            $schs = ShoppingCartHistory::where('transaction_id', $order->id)->get();
            foreach($schs as $sch){
                $admin = Auth::user();
                if ($admin->hasRole(1)) {
                    updateProductStockByData($sch->product_id, 1, $sch->quantity, 1, 4, $order->id, $order->user_id, 'Quotation deleted by admin');
                }
                if ($admin->hasRole(2)) {
                    updateVanStoreProductStockByData($sch->product_id, $sch->quantity, 1, 4, $order->id, $admin->id, 'Quotation deleted by van store');
                }
            }

            ShoppingCartHistory::where('transaction_id', $order->id)->delete();
            Quotation::where('transaction_id', $order->id)->delete();
            $order->delete();

            $response['message'] = 'Quotation deleted!';
            $status = $this->successStatus;
        }else{
            $response['message'] = 'Quotation not exist against this id!';
            $status = $this->errorStatus;
        }

        return response()->json(['result'=>$response], $status);
    }
    
    
    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function getQuotation($id)
    {
        $id = decodeId($id);
        $quotation = Quotation::where('transaction_id', $id)->first();

        $order = Transaction::with(['cart'])->find($id);
        $order->cart->user_details = unserialize($order->cart->user_details);
        $order->cart->cart_details = unserialize($order->cart->cart_details);
        $quotationData['transaction_id'] = $id;
        $quotationData['transaction_details'] = $order->toJson();

        if ($quotation) {
            //$quotation->update($quotationData);
        }else{
            Quotation::create($quotationData);
        }
        
        
        $quotation = Quotation::where('transaction_id', $id)->first();
        $order = json_decode($quotation->transaction_details);

        return view($this->resource . '/quotation-invoice', compact('order'));
    }
    
    /**
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function updateQuotation(Request $request)
    {
        $id = $request->order_id;
        $quotation = Quotation::where('transaction_id', $id)->first();
        
        if ($quotation) {
            $transactionDetails = json_decode($quotation->transaction_details);
            //$transactionDetails->cost = $request->subtotal;
            //$transactionDetails->discount = $request->discount;
            //$transactionDetails->tax = $request->tax;
            $transactionDetails->amount = $request->amount;
            //dd();
            foreach($request->products as $productKey => $product) {
                //dd($product);
                if (@$transactionDetails->cart->cart_details && count($transactionDetails->cart->cart_details)>0) {
                    foreach ($transactionDetails->cart->cart_details as $cartKey => $cart) {
                        if ($cart->id == $productKey) {
                            $productDetail = $transactionDetails->cart->cart_details[$cartKey];
                            $productDetail->name = $product['name'];
                            $productDetail->price = $product['price'];
                            $productDetail->quantity = $product['quantity'];
                            $productDetail->total = $product['total'];
                            break;
                        }
                    }
                }
            }
            $quotation->update(['transaction_details' => json_encode($transactionDetails)]);
            
            Session::flash('success', 'Quotation successfully updated!');  
        } else {
            Session::flash('error', 'Quotation not update!');  
        }
        
        return redirect('admin/admin-orders/quotation/' . Hashids::encode($id));
    }

    /**
     * Get Product Row
     */
    public function getProductRow()
    {
        $products = Product::has('quantity')->pluck('name', 'id')->prepend('Select Product', '');
        
        return response()->json([
            'success'  => true,
            'html'  => view($this->resource . '.product-row', get_defined_vars())->render()
        ], $this->successStatus);
    }
    
    /**
     * Get Product Details
     * @param integer $id
     */
    public function getProductDetails($id)
    {
        $product = Product::with('quantity', 'tax_rate')->find($id);
        $product->final_quantity = 0;
        
        $admin = Auth::user();
        if ($admin->hasRole(1)) {
            if ($product->quantity) {
                $product->final_quantity = $product->quantity->quantity;
            }
        }
        if ($admin->hasRole(2)) {
            $vsp = VanStoreProduct::where(['product_id' => $id, 'admin_id' => $admin->id])->first();
            if ($vsp) {
                $product->final_quantity = $vsp->quantity;
            }
        }
        
        if ($product) { 
            return response()->json([
                'success'  => true,
                'product'  => $product
            ], $this->successStatus);
        }
        
        return response()->json([
            'success'  => false,
        ], $this->successStatus);
    }
    
    /**
     * change status
     */
    public function updateProductCourier(Request $request)
    {
        $cart = ShoppingCart::where('id', $request->cart_id)->first();
        if ($cart) {
            $cart_details = [];
            $products = unserialize($cart->cart_details);
            foreach ($products as $product) {
                if ($product['id'] == $request->product_id) {
                    unset($product['courier']);
                    if ($request->courier_id > 0)
                        $product['courier'] = Courier::find($request->courier_id)->toArray();

                    $cart_details[] = $product;
                } else {
                    $cart_details[] = $product;
                }
            }
            $cart->cart_details = serialize($cart_details);
            $cart->save();
        }

        return 'true';
    }

    public function changeCourier()
    {
        $courier = Courier::where('id',request()->status)->first();

        $cartid = decodeId(\request()->cart_id);
        switch ($courier->type){
            case 'hermes';
                return $this->hermesCourierSystem($cartid,$courier->type);
                break;
            default:
                return 'false';
        }
    }

    public function hermesCourierSystem($cartid,$type)
    {

        $order = Transaction::with(['cart', 'purchasedItems.product.product_images'])->find($cartid);
        $cart = @$order->cart;
        $user = unserialize(@$cart->user_details);

        $dataRecive = (new \App\Http\Controllers\HomeController())->createParcel($user);

        Transaction::where('id', $cartid)->update([
            'barcode' => $dataRecive['barcode'],
            'label_image' => $dataRecive['image'],
            'courier_type' => $type
        ]);
        return 'true';
    }

    public function orderPrint($id)
    {
        $id = decodeId($id);


        $couriers = Courier::pluck('name', 'id')->prepend('Select Courier', '');
        $order = Transaction::with(['cart', 'purchasedItems.product.product_images'])->find($id);

         $vatCharges=TaxRate::select('rate')->where('id',1)->first();
        $vatCharges=(int)$vatCharges->rate;
        return view($this->resource . '/invoice-print', compact('order', 'couriers','vatCharges'));
    }
    
    public function orderQuotationPrint($id)
    {
        $id = decodeId($id);
        $quotation = Quotation::where('transaction_id', $id)->first();
        $order = json_decode($quotation->transaction_details);

        return view($this->resource . '/invoice-quotation-print', compact('order'));
    }

    public function updateOrderStatus($id) {

         $cart = ShoppingCart::where('id', $id)->first();
         $cart->delivery_status = request()->status;
         $cart->save();
         $status = request()->status;
         $userData = User::whereId($cart->user_id)->first();
         $url =   url('/get-invoice-detail',Hashids::encode(request()->transaction_id));
         $user = 'khaleelrehman110@gmail.com';
         $data = [
            'email_from'    => 'baadrayltd@gmail.com',
            'email_to'      => $userData->email,
            'email_subject' => 'Order Delivery',
            'user_name'     => 'User',
            'final_content' => "<p><b>Dear User</b></p>
                                    <p>Your Order has been $status</p><br><a href='$url'>Click Here For Invoice</>",
        ];

         $data1 = [
            'email_from'    => 'baadrayltd@gmail.com',
            'email_to'      => 'aqsintetrnationalstore@gmail.com',
            'email_subject' => 'Order Delivery',
            'user_name'     => 'User',
            'final_content' => "<p><b>Dear Admin</b></p>
                                    <p>An Order is been $status</p>",
        ];
         
        try{
            Email::sendEmail($data);
        }
        catch(Exception $e)
        {
            Log::error('Order Email error: ' . $e->getMessage());
        }
        
        try{
            Email::sendEmail($data1);
        }
        catch(Exception $e)
        {
            Log::error('Order Email error: ' . $e->getMessage());
        }
        
      
        
        return 'true';
    }

    public function updateInvoice()
    {
        $productsName = request()->product_name;
        $productId = \request()->product_id;
        $price = \request()->price;

        $data = array_map(function($pric,$prductname,$productid){
            return [
                'id' => $productid,
                'name' => $prductname,
                'price' => $pric,
            ];

        },$price,$productsName,$productId);
        Transaction::where('id',\request()->order_id)->update(['updated_columns' => json_encode($data)]);
        return back()->with('success','Successfully updated');
    }
    
    public function getWalletAmount($id)
    {
        $walletAmount = 'Wallet: £0.00';
        $payAmount = 'To Pay: £0.00';
        
        $wallet = getWholsellerDataWallet($id);
        $twopay = get2PayAmount($id);
        
        if ($wallet<0) {
            $walletAmount = '-£'.number_format(abs($wallet), 2);
        } else {
            $walletAmount = '£'.number_format($wallet, 2);
        }
        
        if ($twopay<0) {
            $payAmount = '-£'.number_format(abs($twopay), 2);
        } else {
            $payAmount = '£'.number_format($twopay, 2);
        }
        
        return '<a href="'. url("admin/orders?type=admin_order&payment_method=wallet&user_id=" . Hashids::encode($id)) .'" target="_blank">Wallet: '. $walletAmount .'</a>'
                . '<br/>'
                . '<a href="'. url("admin/orders?type=admin_order&payment_method=2pay&user_id=" . Hashids::encode($id)) .'" target="_blank">To Pay: '. $payAmount .'</a>';
    }


}
