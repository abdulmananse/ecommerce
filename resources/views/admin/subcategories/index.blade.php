@extends('admin.layouts.app')

@section('content')
<section id="main-content" >
    <section class="wrapper">
        <div class="row">
            <div class="col-md-12">
                <!--breadcrumbs start -->
                <ul class="breadcrumb">
                    <li><a href="{{ url('admin/dashboard') }}"><i class="fa fa-home"></i> Dashboard</a></li>
                    <li class="active">Categories</li>
                </ul>
                <!--breadcrumbs end -->
            </div>
        </div>

         <div class="row">
            <div class="col-sm-12">
                <section class="panel">
                    <header class="panel-heading">
                       Sub Categories
                        <span class="tools pull-right">
                            <a href="{{ url('admin/subcategories/create') }}" class="btn btn-info btn-sm" data-toggle="tooltip" title="Add New Category">
                                <i class="fa fa-plus" aria-hidden="true"></i> Add New
                            </a>
                         </span>
                    </header>
                    <div class="panel-body">
                        <table id="datatable" class="table table-bordered table-striped">
                            <thead>
                            <tr>
                                <th>Sub Category Name</th>
                                <th>Category Name</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                                <tr class="odd"><td valign="top" colspan="6" class="dataTables_empty">No data available in table</td></tr>
                            </tbody>
                            <tfoot>
                            <tr>
                                <th>Sub Category Name</th>
                                <th>Category Name</th>
                                <th>Actions</th>
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
<script type="text/javascript">
$("document").ready(function () {
        var datatable_url = "{{url('admin/subcategories')}}";
        var datatable_columns = [
            {data: 'sub_name', width: '10%',orderable: false, searchable:false},
            {data: 'categories.name'},
            {data: 'action',  width: '10%', orderable: false, searchable: false}

            ];

        $('#datatable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: datatable_url,
                data : function(d){
                    if($(".filter_by_store").val() != ''){
                        d.columns[2]['search']['value'] = $(".filter_by_store option:selected").text();
                    }
                    }
            },
            columns: datatable_columns,
            "order": []
        });

     //   $("#datatable_length").append('{!! Form::select("type", getStoresFilterDropdown(), null, ["class" => "form-control input-sm filter_by_store","style"=>"margin-left: 20px;"]) !!}');

        var reload_datatable = $("#datatable").dataTable( { bRetrieve : true } );

        $(document).on('change', '.filter_by_store', function (e) {
            reload_datatable.fnDraw();
        });

      });
</script>
@endsection
