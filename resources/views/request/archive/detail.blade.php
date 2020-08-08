@extends('frontend.home')
@section('title', 'Detail Pengajuan')
@section('title-content', 'Detail ' . $category->name)
@section('content')

@if(count($errors)>0)
@foreach($errors->all() as $error)
<div class="col-md-12 col-sm-12 col-xl-12">
    <div class="alert alert-danger" role="alert">
        {{ $error }}
    </div>
</div>
@endforeach
@endif

<div class="col-sm-12 col-md-12 col-xl-12">
    <a href="{{ route('request.pengajuan.archive') }}" class="btn btn-primary"><i class="fa fa-arrow-left"></i> Back</a>
    @if($request->status === 'cancel')
    <span>Please Contact Admin For Information Because Your Request is <strong class="text-danger">Cancel</strong></span>
    @else
    @foreach($responsibles as $responsible)
    @if($responsible->status == 'waiting' || $responsible->status == 'perbaikan')
    <a href="{{ route('requestby.category.edit', $request->id) }}" class="btn btn-warning"><i class="fa fa-edit"></i> Edit</a>
    @break
    @elseif($responsible->status == 'revision')
    <a href="{{ route('requestby.category.revision', $request->id) }}" class="btn btn-warning"><i class="fa fa-edit"></i> Revisi </a>
    @break
    @elseif($responsible->status == 'hold')
    <span>Please Contact Admin For Information Because Your Request is <strong class="text-danger">Hold</strong></span>
    @break
    @else
    @endif
    @endforeach
    @endif
</div>

<div class="row">
    <!-- <div class="contain-content"></div> -->
    <div class="x_panel">
        <div class="x_title">
            <h2>Perihal <small>{{$request->perihal}}</small></h2>
            <div class="clearfix"></div>
        </div>
        <div class="x_content">

            <section class="content invoice">
                <!-- title row -->
                <div class="row" style="margin-bottom:20px;">
                    <div class="col-xs-12 invoice-header">
                        <h1>
                            <img src="{{ asset($request->applicant->image) }}" alt="" class="img img-avatar" style="max-width:40px;"> {{$request->applicant->name}}.
                        </h1>
                    </div>
                    <!-- /.col -->
                </div>
                <!-- info row -->
                <div class="row invoice-info">
                    <!-- /.col -->
                    <div class="col-sm-4 invoice-col" style="margin-bottom: 20px;">
                        <b>{{ $request->code }}</b>
                        <br>
                        <br>
                        <b>Status Pengajuan:</b>
                        @if($request->status === 'on proses')
                        <span class="btn btn-primary btn-xs">{{$request->status}}</span>
                        @elseif($request->status == 'revision')
                        <span class="btn btn-warning btn-xs">{{$request->status}}</span>
                        @elseif($request->status == 'perbaikan')
                        <span class="btn btn-danger btn-xs">{{$request->status}}</span>
                        @elseif($request->status == 'hold')
                        <span class="btn btn-info btn-xs">{{$request->status}}</span>
                        @elseif($request->status == 'approve')
                        <span class="btn btn-success btn-xs">{{$request->status}}</span>
                        @endif
                        <br>
                        <b>Perihal:</b> {{$request->perihal}}
                        <br>
                        <b>Total:</b> {{'Rp ' . number_format($request->total)}}
                        <br>
                        <b>Terbilang:</b> {{$request->amount}}
                        <br>
                        <b>Date:</b> {{ date('g F Y', strtotime($request->start_date))}}
                        <br>
                        <b>Expire:</b> {{ date('g F Y', strtotime($request->expire_date))}}
                        <br>
                    </div>
                    <!-- /.col -->
                    <!-- /.col -->
                    <div class="col-sm-4 invoice-col">

                        <b>Penanggung Jawab</b>
                        <br>
                        <br>
                        @foreach($responsibles as $responsible)
                        <b>{{ $responsible->position }}:</b>
                        @if($responsible->status == 'waiting')
                        <span class="btn btn-primary btn-xs">{{$responsible->status}}</span>
                        @elseif($responsible->status == 'revision')
                        <span class="btn btn-warning btn-xs">{{$responsible->status}}</span>
                        @elseif($responsible->status == 'perbaikan')
                        <span class="btn btn-danger btn-xs">{{$responsible->status}}</span>
                        @elseif($responsible->status == 'hold')
                        <span class="btn btn-info btn-xs">{{$responsible->status}}</span>
                        @elseif($responsible->status == 'acc')
                        <span class="btn btn-success btn-xs">{{$responsible->status}}</span>
                        @endif
                        <br>
                        @endforeach
                    </div>
                    <!-- /.col -->
                    @if($request->catatan != '')
                    <div class="col-sm-4 invoice-col">

                        <b>Catatan</b>
                        <br>
                        <br>
                        <span class="text-danger">{{$request->catatan}}</span>
                    </div>
                    @endif
                    <!-- /.col -->
                </div>
                <!-- /.row -->

                <!-- Table row -->
                <div class="row">
                    <div class="table-overflow">
                        <div class="table">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th style="width:25%">Nama Item</th>
                                        <th>Qty</th>
                                        <th>Satuan</th>
                                        <th>Harga Satuan</th>
                                        <th>Jumlah Harga</th>
                                        <th style="width:25%">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 1; ?>
                                    @foreach($items as $result => $item)
                                    <tr>
                                        <td>{{ $i++}}</td>
                                        <td>{{ $item->name . '/' . $item->merk . '/' . $item->spec}}</td>
                                        <td>{{ $item->qty }}</td>
                                        <td>{{ $item->unit }}</td>
                                        <td>{{ number_format($item->price) }}</td>
                                        <td>{{ number_format($item->sub) }}</td>
                                        <td>{{ $item->desc }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                    </div>
                    <!-- /.col -->
                </div>
                <!-- /.row -->

                <!-- Row -->
                <div class="row">
                    <!-- /.col -->
                    <div class="col-xs-12 col-md-6 col-md-offset-6">
                        <div class="table-responsive">
                            <table class="table">
                                <tbody>
                                    <tr>
                                        <th style="width:50%">Total:</th>
                                        <td>{{ 'Rp ' . number_format($request->total) }}</td>
                                    </tr>
                                    <tr>
                                        <th>Terbilang</th>
                                        <td>{{ $request->amount }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- /.col -->
                </div>
                <!-- /.row -->

                <!-- this row will not appear when printing -->
                <div class="row no-print">

                    <div class="col-xs-12">
                        <form action="{{ route('request.pengajuan.approve') }}" method="post">
                            @csrf
                            @method('patch')
                            <div class="row">
                                <div class="col-md-6 col-xs-12">
                                    <input type="hidden" name="request_id" value="{{ $request->id }}">
                                    <a href="{{route('requestby.category.pdf', $request->id)}}" target="_blank" class="btn btn-primary" style="margin-right: 5px;"><i class="fa fa-download"></i> Generate PDF</a>
                                    @if($revision !== null)
                                    <a href="{{route('request.pengajuan.detail-archive', $revision->beforeRev->id)}}" target="_blank" class="btn btn-primary" style="margin-right: 5px;"><i class="fa fa-download"></i> Before Revision</a>
                                    @endif
                                    @if($request->status == 'approve')
                                    @if(!$request->report)
                                    @if($request->applicant_id == Auth::id() || Auth::user()->level->capacity == 90 || Auth::user()->level->capacity == 20)
                                    <a href="{{route('request.report.create', $request->id)}}" target="_blank" class="btn btn-primary" style="margin-right: 5px;"><i class="fa fa-file-text"></i> Buat Laporan</a>
                                    @endif
                                    @else
                                    <a href="{{route('request.report.show', $request->report->id)}}" target="_blank" class="btn btn-primary" style="margin-right: 5px;"><i class="fa fa-eye"></i> Lihat Laporan</a>
                                    @endif
                                    @endif
                                </div>
                                <div class="col-md-6 col-xs-12">

                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>


@endsection



@section('css')
<!--  -->
@endsection

@section('js')

<script>
    $(function() {

        $('.btn-confirm').click(function(e) {
            e.preventDefault();
            var btnConfirm = $(this);
            var btnChange = $('#btn-confirm');

            btnChange.attr('name', btnConfirm.attr('name'));
            btnChange.val(btnConfirm.attr('value'));
            btnChange.removeClass().addClass(btnConfirm.attr('class'));
            btnChange.html(btnConfirm.html());
        });
    });
</script>
@endsection