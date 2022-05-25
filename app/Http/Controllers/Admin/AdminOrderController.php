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
use App\Models\ShoppingCart;
use App\Models\ShoppingCartHistory;
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
            if ($id) {
                $user_id = decodeId($id);

                $orders =$orders->whereUserId($user_id)->orderBy('id', 'desc');
            } else {
                $orders =$orders->orderBy('id', 'desc');
            }

            return Datatables::of($orders->get())
                ->addColumn('orders_details', function ($order) {
                    return '<span class="details-control"></span>';
                })
                ->addColumn('username', function ($order) {
                    $username = '';
                    if ($order->cart) {
                        $user = unserialize($order->cart->user_details);
                        $username = @$user["first_name"].' '.@$user["last_name"];
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
                    $amount = $order->amount > 0 ? $order->amount : 0;
                    return "<a style='color: $color'>".'£'.".$amount</a>";
                })
                ->addColumn('barcode_image', function ($order) {
                    $user =  User::where('id',$order->user_id)->first();

                    if($user and $order->label_image and $user->type !='wholesaler'){
                        return '<a href="'.url($order->label_image).'" download><img src="'.url($order->label_image).'" alt="'.$order->label_image.'" width="100" height="200"></a>';
                    }else{
                        return '';
                    }
                })
                ->addColumn('status', function ($order) {

                        $options = '<option value="">Select Status</option>';


                         $cartid=$order->cart['id'];
                         $transaction_id = $order->id;
                        foreach (['pending','dispatched','delivered'] as $value){
                             $selected ='';
                             if($value ==='pending'){
                                  $selected = ($order->cart and $order->cart->delivery_status == "pending") ? "selected" : "";

                             }

                             if ($value ==='dispatched'){
                                 $selected = ($order->cart and $order->cart->delivery_status == "dispatched") ? "selected" : "";

                             }

                             if($value ==='delivered'){
                                   $selected = ($order->cart and $order->cart->delivery_status == "delivered") ? "selected" : "";
                             }

                            $options.=' <option   '.$selected.' value='.$value.'>'.$value.'</option>';
                        }

                      return '<select name="status" class="status_update" data-id='.$cartid.' data-transaction-id='.$transaction_id.'>
                               ' . $options . '
                            </select>';

                })
                ->addColumn('action', function ($order) {
                    $label='';
                    if($order->label_image){
                        $label = url($order->label_image);
                    }
                    $action = '';

                    $action .='<a href="admin-orders/' . Hashids::encode($order->id) . '" class="text-success btn-order" data-toggle="tooltip" title="View Quotation"><i class="fa fa-eye fa-lg"></i></a>';
                    $action .='<a href="admin-orders/quotation/' . Hashids::encode($order->id) . '" class="text-primary btn-order" data-toggle="tooltip" title="Generate Invoice"><i class="fa fa-file fa-lg"></i></a>';
                    $action .='<a href="' .url('admin/update-status-order/'.Hashids::encode($order->id) ). '" class="text-success btn-order" data-toggle="tooltip" title="Update Status"><i class="fa fa-edit"></i></a>';
                    // $action .='<a href="' .url('admin/update-delivery-status/'.Hashids::encode($order->cart_id) ). '" class="text-success btn-order" data-toggle="tooltip" title="Update Delivery Status"><i class="fa fa-edit"></i></a>';

                    if(!empty($label)){
                        $action .='|<a href="javascript:void(0)" image-url="'.$label.'" class="text-success bt-download" data-toggle="tooltip" title="Download"><i class="fa fa-print fa-lg"></i></a>';
                    }
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
        $customers = OrderUser::pluck('owner_name', 'id')->prepend('Select Customer', '');
        $products = Product::pluck('name', 'id')->prepend('Select Product', '');
        
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
        
        $customerId = $request->customer_id;
        $customer = OrderUser::find($customerId);
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
            $transaction['cost']      = number_format($cost, 2);
            $transaction['discount']  = number_format($discount, 2);
            $transaction['tax']       = number_format($tax, 2);
            $transaction['paypal_id'] = 0;
            $transaction['amount']    = number_format($totalAmount, 2);
            $transaction['is_latest']  = 1;
            $transaction['trans_details']  = null;
            $transaction['type']  = 'admin_order';
            // create transaction
            $transaction = Transaction::create($transaction);
            
            if ($transaction) {
                $historyData = collect($historyData);
                $historyData = $historyData->map(function ($item) use ($transaction) {
                    $item['transaction_id'] = $transaction->id;
                    return $item;
                });
                ShoppingCartHistory::insert($historyData->toArray());
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
     * Display the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function getQuotation($id)
    {
        $id = decodeId($id);
        $quotation = Quotation::where('transaction_id', $id)->first();
        if (!$quotation) {
            $order = Transaction::with(['cart'])->find($id);
            
            $order->cart->user_details = unserialize($order->cart->user_details);
            $order->cart->cart_details = unserialize($order->cart->cart_details);
            
            $quotationData['transaction_id'] = $id;
            $quotationData['transaction_details'] = $order->toJson();
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
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     *
     * @return \Illuminate\View\View
     */
    public function edit($id)
    {
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
    }
    
    /**
     * Get Product Row
     */
    public function getProductRow()
    {
        $products = Product::pluck('name', 'id')->prepend('Select Product', '');
        
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


}
