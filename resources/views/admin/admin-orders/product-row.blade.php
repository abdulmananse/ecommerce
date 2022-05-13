<tr class='product_row'>
    <td class="invoice">
        {!! Form::select('product_id[{0}]', $products, null, ['class' => 'form-control select2 products', 'style' => 'width:250px', 'required' => 'required']) !!}
        <label id="product_id[{0}]-error" class="error" for="product_id[{0}]"></label>
    </td>
    <td class="text-center product_unit_price">0</td>
    <td class="text-center product_quantity">
        <input required type='number' style='width:50px' name='product_quantity[{0}]' class='product_quantity' value="1" min="1" />
        <label id="product_quantity[{0}]-error" class="error" for="product_quantity[{0}]"></label>
    </td>
    <td class="text-center product_total">0</td>
    <td class="text-center"><i class="btn btn-sm fa fa-close removeRow text-danger"></i></td>
</tr>