@extends('admin.layouts.app')

@section('content')
<section id="main-content" >
    <section class="wrapper">
        <div class="row">
            <div class="col-md-12">
                <!--breadcrumbs start -->
                <ul class="breadcrumb">
                    <li><a href="{{ url('admin/dashboard') }}"><i class="fa fa-home"></i> Dashboard</a></li>
                    <li class="active">Wallet Statment</li>
                </ul>
                <!--breadcrumbs end -->
            </div>
        </div>                
        
         <div class="row">
            <div class="col-sm-12">
                <section class="panel">
                    <header class="panel-heading">Wallet Statment</header>
                    <div class="panel-body">
                        <table id="datatable" class="table table-bordered table-striped">
                            <thead>
                            <tr>
                                <th width="10%">Date</th>
                                <th width="10%">Debit</th>
                                <th width="10%">Credit</th>
                                <th>Note</th>
                            </tr>
                            </thead>
                            <tbody>
                                @forelse($wallets as $wallet)
                                <tr>
                                    <td>{{ date('d-m-Y', strtotime($wallet->date)) }}</td>
                                    <td align="center" style="color:{{ ($wallet->type=='2pay')?'red':'' }};">{{ $wallet->debit }}</td>
                                    <td align="center">{{ $wallet->credit }}</td>
                                    <td>{{ $wallet->note }}</td>
                                </tr>
                                @empty
                                <tr class="odd"><td valign="top" colspan="4" class="dataTables_empty">No data available in table</td></tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                            <tr>
                                <th>Date</th>
                                <th>Total: ({{ $wallets->sum('debit') }})</th>
                                <th>Total: ({{ $wallets->sum('credit') }})</th>
                                <th>Balance: ({{ $wallets->sum('credit') - $wallets->sum('debit') }})</th>
                            </tr>
                          </tfoot>
                        </table>
                    </div>
                </section>
            </div>
        </div>   
            
    </section>
</section>

@endsection


@section('scripts')

<script>

</script>

@endsection