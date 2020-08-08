<?php

namespace App\Http\Controllers;

use App\Product;
use App\RequestApprove;
use App\RequestCategory;
use App\RequestItems;
use App\RequestResponsible;
use App;
use App\Notification;
use App\RequestConnection;
use App\RequestReport;
use App\User;
use PDF;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;

class RequestByCategoryController extends Controller
{

    function numberToRomanRepresentation($number)
    {
        $map = array('M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1);
        $returnValue = '';
        while ($number > 0) {
            foreach ($map as $roman => $int) {
                if ($number >= $int) {
                    $number -= $int;
                    $returnValue .= $roman;
                    break;
                }
            }
        }
        return $returnValue;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(RequestCategory $id)
    {
        $requests = \App\Request::where('category_id', 'like', $id->id)->orderBy('created_at', 'desc')->paginate(10);
        if (Auth::user()->level->capacity == 10) {
            $requests = \App\Request::where('applicant_id', Auth::id())->where('category_id', 'like', $id->id)->orderBy('created_at', 'desc')->paginate(10);
        }
        // dd($requests[0]);
        $category = $id;
        return view('request.request_category.index', compact('requests', 'category'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(RequestCategory $id)
    {
        $check_responsible = RequestResponsible::where('category_id', '=', $id->id)->get();


        if (count($check_responsible) == 0) {
            return redirect()->back()->withWarning('Pengajuan Belum Memiliki Penanggung Jawab');
        }

        $category = $id;
        $users = User::all();
        $items = Product::all();
        $code = \App\Request::where('code', 'like', "%" . $id->code . "%")->orderBy('id', 'desc')->first();
        if ($code) {
            $code = explode('/', $code->code);
            $number = str_replace('Rev-', '', $code[0]);
            if (!$number || $number == '') {
                $number = '001';
            } else {
                $number = sprintf("%'03d", ($number + 1));
            }
            $bulan = $this->numberToRomanRepresentation(date('m'));
            $thn = date('Y');
            $code = $number . $category->code . $bulan . '/' . $thn;
        } else {
            $code = '001' . $category->code . $this->numberToRomanRepresentation(date('m')) . '/' . date('Y');
        }

        if ($id->types->name == 'pembelian') {
            return view('request.create.pembelian', compact('code', 'users', 'category', 'items'));
        } elseif ($id->types->name == 'biaya') {
            return view('request.create.biaya', compact('code', 'users', 'category', 'items'));
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'category_id' => 'required',
            'creator_id' => 'required',
            'code' => 'required',
            'applicant_id' => 'required',
            'perihal' => 'required',
            'date' => 'required',
            'total' => 'required',
            'amount' => 'required',
        ]);

        $category_request = RequestCategory::findOrFail($request->category_id);

        if (preg_match('/pembelian/', $category_request->types->name)) {

            $this->validate($request, [
                'item' => 'required',
                'unit' => 'required',
                'qty' => 'required',
                'price' => 'required',
                'sub' => 'required',
                'desc' => 'required',
            ]);
        } elseif (preg_match('/biaya/', $category_request->types->name)) {

            $this->validate($request, [
                'item' => 'required',
                'name' => 'required',
                'merk' => 'required',
                'spec' => 'required',
                'unit' => 'required',
                'qty' => 'required',
                'price' => 'required',
                'sub' => 'required',
                'desc' => 'required',
            ]);
        }



        $date = explode(' - ', $request->date);
        $start_date = explode('/', $date[0]);
        $start_date = implode('-', [$start_date[2], $start_date[0], $start_date[1]]);
        $start_date = $start_date . ' ' . date('H:i:s');
        $expire_date = explode('/', $date[1]);
        $expire_date = implode('-', [$expire_date[2], $expire_date[0], $expire_date[1]]);
        $expire_date = $expire_date . ' ' . date('H:i:s');


        $data = [
            'category_id' => $request->category_id,
            'creator_id' => $request->creator_id,
            'applicant_id' => $request->applicant_id,
            'code' => $request->code,
            'perihal' => $request->perihal,
            'start_date' => $start_date,
            'expire_date' => $expire_date,
            'total' => $request->total,
            'amount' => $request->amount,
        ];


        // INSERT DATA TO REQUESTS TABLE
        \App\Request::create($data);
        // GET DATA REQUEST FROM LAST INPUT
        $request_code = \App\Request::where('code', $request->code)->first();
        // SEARCH DATA RESPONSIBLE REQUEST FROM CATEGORY ID
        $responsibles = RequestResponsible::where('category_id', $request->category_id)->get();
        // INSERT DATA RESPONSIBLE IN 1 ARRAY
        $approvers = [];
        foreach ($responsibles as $responsible) {
            if ($request->applicant_id == $responsible->user_id) {
                continue;
            }
            $temp = [
                'request_id' => $request_code->id,
                'user_id' => $responsible->user_id,
                'position' => $responsible->as,
                'subject' => $responsible->subject,
                'priority' => $responsible->priority,
                'created_at' => now(),
                'updated_at' => now()
            ];
            array_push($approvers, $temp);
        }

        // SEARCH DATA USER WHERE LEVEL 'COMMON ADMIN = 3', 'MANAGER = 2', 'ADMIN = 1'
        $users =  $users = User::where('level_id', 1)->orWhere('level_id', 2)->orWhere('level_id', 3)->get();
        // dd($users);
        $notification = [];
        // INSERT USER TO NOTIFICATION ARRAY
        for ($i = 0; $i < count($users); $i++) {
            $temp = [
                'user_id' => $users[$i]['id'],
                'request_id' => $request_code->id,
                'request_report_id' => 0,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ];
            array_push($notification, $temp);
        }


        // PREPARE REQUEST ITEMS
        $data_items = [];

        $items = $request->item;
        $names = $request->name;
        $merks = $request->merk;
        $specs = $request->spec;
        $units = $request->unit;
        $qtys = $request->qty;
        $prices = $request->price;
        $subs = $request->sub;
        $descs = $request->desc;

        // dd($request->all());
        // dd($names);
        for ($i = 0; $i < count($items); $i++) {

            $item_id = Product::whereId($items[$i])->first();
            if ($item_id) {
                $name = $item_id->name;
                $merk = $item_id->brand->name;
                $spec = $item_id->spec;
            } else {
                $name = $names[$i];
                $merk = $merks[$i];
                $spec = $specs[$i];
            }

            $temp = [
                'request_id' => $request_code->id,
                'items' => $items[$i],
                'name' => $name,
                'merk' => $merk,
                'spec' => $spec,
                'unit' => $units[$i],
                'qty' => $qtys[$i],
                'price' => $prices[$i],
                'sub' => $subs[$i],
                'desc' => $descs[$i],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            array_push($data_items, $temp);
        }


        // INSERT DATA RESPONSIBLE TO APPROVAL REQUEST
        RequestApprove::insert($approvers);

        // INSERT DATA NOTIFICATION TO NOTIFICATION REQUEST
        Notification::insert($notification);

        // INSERT DATA REQUEST ITEM TO REQUEST ITEMS TABLE
        RequestItems::insert($data_items);



        return redirect()->route('requestby.category.index', $request->category_id)->withSuccess("Pengajuan Has Been Created");
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {

        $notification = Notification::where('request_id', $id)->where('is_read', 0)->get();
        for ($i = 0; $i < count($notification); $i++) {
            if ($notification[$i]['user_id'] == Auth::id()) {
                $update = Notification::whereId($notification[$i]['id']);
                $update->is_read = 1;
                $update->save();
            }
        }

        $request = \App\Request::findOrFail($id);
        $category = RequestCategory::findOrFail($request->category_id);
        $items = RequestItems::where('request_id', $request->id)->get();
        $responsibles = RequestApprove::where('request_id', $request->id)->get();

        return view('request.request_category.detail', compact('request', 'items', 'category', 'responsibles'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $request = \App\Request::findOrFail($id);
        $category = RequestCategory::findOrFail($request->categories->id);
        $items_req = RequestItems::where('request_id', $id)->get();
        $items = Product::all();
        $users = User::all();

        if ($category->types->name == 'pembelian') {
            return view('request.edit.pembelian', compact('request', 'items', 'items_req', 'category', 'users'));
        } elseif ($category->types->name == 'biaya') {
            return view('request.edit.biaya', compact('request', 'items', 'items_req', 'category', 'users'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

        $notification = Notification::where('request_id', $id)->get();
        foreach ($notification as $notif) {
            $update = Notification::find($notif->id);
            $update->is_read = 0;
            $update->save();
        }


        $request_update = \App\Request::whereId($id);

        $this->validate($request, [
            'category_id' => 'required',
            'creator_id' => 'required',
            'code' => 'required',
            'applicant_id' => 'required',
            'perihal' => 'required',
            'date' => 'required',
            'total' => 'required',
            'amount' => 'required',
        ]);

        $category_request = RequestCategory::findOrFail($request->category_id);

        if (preg_match('/pembelian/', $category_request->types->name)) {

            $this->validate($request, [
                'item' => 'required',
                'unit' => 'required',
                'qty' => 'required',
                'price' => 'required',
                'sub' => 'required',
                'desc' => 'required',
            ]);
        } elseif (preg_match('/biaya/', $category_request->types->name)) {

            $this->validate($request, [
                'item' => 'required',
                'name' => 'required',
                'merk' => 'required',
                'spec' => 'required',
                'unit' => 'required',
                'qty' => 'required',
                'price' => 'required',
                'sub' => 'required',
                'desc' => 'required',
            ]);
        }


        $date = explode(' - ', $request->date);
        $start_date = explode('/', $date[0]);
        $start_date = implode('-', [$start_date[2], $start_date[0], $start_date[1]]);
        $start_date = $start_date . ' ' . date('H:i:s');
        $expire_date = explode('/', $date[1]);
        $expire_date = implode('-', [$expire_date[2], $expire_date[0], $expire_date[1]]);
        $expire_date = $expire_date . ' ' . date('H:i:s');

        $data = [
            'category_id' => $request->category_id,
            'creator_id' => $request->creator_id,
            'applicant_id' => $request->applicant_id,
            'code' => $request->code,
            'perihal' => $request->perihal,
            'start_date' => $start_date,
            'expire_date' => $expire_date,
            'total' => $request->total,
            'amount' => $request->amount,
            'catatan' => '',
        ];


        // INSERT DATA TO REQUESTS TABLE
        \App\Request::whereId($id)->update($data);
        // SEARCH DATA APPROVE REQUEST FROM REQUEST APPROVE
        $responsibles = RequestApprove::where('request_id', $id)->get();
        // INSERT DATA CHANGE ALL STATUS APPROVE TO WAITING
        $approvers = [];
        for ($i = 0; $i < count($responsibles); $i++) {
            $data = ['status' => 'waiting'];
            RequestApprove::whereId($responsibles[$i]['id'])->update($data);
        }

        // REUPLOAD STATUS NOTIFICATION
        $notifications = Notification::where('request_id', $id)->get();
        foreach ($notifications as $notification) {
            $notif = Notification::find($notification->id);
            $notif->is_read = 0;
            $notif->save();
        }

        // GET ALL ITEMS AND DELETE ALL
        RequestItems::where('request_id', $id)->delete();

        // INSERT NEW ITEMS
        $data_items = [];


        $items = $request->item;
        $names = $request->name;
        $merks = $request->merk;
        $specs = $request->spec;
        $units = $request->unit;
        $qtys = $request->qty;
        $prices = $request->price;
        $subs = $request->sub;
        $descs = $request->desc;


        $name = '';
        $merk = '';
        $spec = '';
        for ($i = 0; $i < count($items); $i++) {

            $item_id = Product::whereId($items[$i])->first();
            if ($item_id) {
                $name = $item_id->name;
                $merk = $item_id->brand->name;
                $spec = $item_id->spec;
            } else {
                $name = $names[$i];
                $merk = $merks[$i];
                $spec = $specs[$i];
            }

            $temp = [
                'request_id' => $id,
                'items' => $items[$i],
                'name' => $name,
                'merk' => $merk,
                'spec' => $spec,
                'unit' => $units[$i],
                'qty' => $qtys[$i],
                'price' => $prices[$i],
                'sub' => $subs[$i],
                'desc' => $descs[$i],
                'created_at' => now(),
                'updated_at' => now(),
            ];
            array_push($data_items, $temp);
        }

        // dd($data_items);

        // INSERT DATA REQUEST ITEM TO REQUEST ITEMS TABLE
        RequestItems::insert($data_items);

        // return redirect()->route('request.pengajuan.show', $id)->withSuccess("Pengajuan Has Been Updated");
        return redirect()->route('request.pengajuan.show', $id)->withSuccess("Pengajuan Has Been Updated");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $request = \App\Request::findOrFail($id);
        $request_name = $request->categories->name;

        RequestItems::where('request_id', $id)->delete();
        RequestApprove::where('request_id', $id)->delete();
        Notification::where('request_id', $id)->delete();
        $request->delete();


        return redirect()->back()->withSuccess("$request_name, Has Been Deleted");
    }

    public function revision($id)
    {
        $request = \App\Request::findOrFail($id);
        $code = $request->code;
        if (preg_match('/Rev-/i', $code)) {
            $code = str_replace('Rev-', '', $code);
        }
        $code = 'Rev-' . $code;
        $category = RequestCategory::findOrFail($request->categories->id);
        $items_req = RequestItems::where('request_id', $id)->get();
        $items = Product::all();
        $users = User::all();

        if ($category->types->name == 'pembelian') {
            return view('request.revision.pembelian', compact('request', 'items', 'items_req', 'category', 'users', 'code'));
        } elseif ($category->types->name == 'biaya') {
            return view('request.revision.biaya', compact('request', 'items', 'items_req', 'category', 'users', 'code'));
        }
    }

    public function updateRev(Request $request)
    {
        $this->validate($request, [
            'category_id' => 'required',
            'creator_id' => 'required',
            'code' => 'required',
            'applicant_id' => 'required',
            'perihal' => 'required',
            'date' => 'required',
            'total' => 'required',
            'amount' => 'required',
        ]);

        // Update Status and Status Revision
        $pengajuan_beforeRev = \App\Request::findOrFail($request->request_id);
        // dd($pengajuan_beforeRev->is_revision);
        $pengajuan_beforeRev->is_revision = 1;
        $pengajuan_beforeRev->status = "cancel";
        $pengajuan_beforeRev->save();

        $category_request = RequestCategory::findOrFail($request->category_id);

        if (preg_match('/pembelian/', $category_request->types->name)) {

            $this->validate($request, [
                'item' => 'required',
                'unit' => 'required',
                'qty' => 'required',
                'price' => 'required',
                'sub' => 'required',
                'desc' => 'required',
            ]);
        } elseif (preg_match('/biaya/', $category_request->types->name)) {

            $this->validate($request, [
                'item' => 'required',
                'name' => 'required',
                'merk' => 'required',
                'spec' => 'required',
                'unit' => 'required',
                'qty' => 'required',
                'price' => 'required',
                'sub' => 'required',
                'desc' => 'required',
            ]);
        }


        $date = explode(' - ', $request->date);
        $start_date = explode('/', $date[0]);
        $start_date = implode('-', [$start_date[2], $start_date[0], $start_date[1]]);
        $start_date = $start_date . ' ' . date('H:i:s');
        $expire_date = explode('/', $date[1]);
        $expire_date = implode('-', [$expire_date[2], $expire_date[0], $expire_date[1]]);
        $expire_date = $expire_date . ' ' . date('H:i:s');


        $data = [
            'category_id' => $request->category_id,
            'creator_id' => $request->creator_id,
            'applicant_id' => $request->applicant_id,
            'code' => $request->code,
            'perihal' => $request->perihal,
            'start_date' => $start_date,
            'expire_date' => $expire_date,
            'total' => $request->total,
            'amount' => $request->amount,
        ];

        // INSERT DATA TO REQUESTS TABLE
        $pengajuan_afterRev = \App\Request::create($data);
        // GET DATA REQUEST FROM LAST INPUT
        $request_code = \App\Request::where('code', $request->code)->first();
        // SEARCH DATA RESPONSIBLE REQUEST FROM CATEGORY ID
        $responsibles = RequestResponsible::where('category_id', $request->category_id)->get();
        // INSERT DATA RESPONSIBLE IN 1 ARRAY
        $approvers = [];
        foreach ($responsibles as $responsible) {
            if ($request->applicant_id == $responsible->user_id) {
                continue;
            }
            $temp = [
                'request_id' => $request_code->id,
                'user_id' => $responsible->user_id,
                'position' => $responsible->as,
                'subject' => $responsible->subject,
                'priority' => $responsible->priority,
                'created_at' => now(),
                'updated_at' => now()
            ];
            array_push($approvers, $temp);
        }

        // SEARCH DATA USER WHERE LEVEL 'COMMON ADMIN = 3', 'MANAGER = 2', 'ADMIN = 1'
        $users =  $users = User::where('level_id', 1)->orWhere('level_id', 2)->orWhere('level_id', 3)->get();
        $notification = [];
        // INSERT USER TO NOTIFICATION ARRA
        for ($i = 0; $i < count($users); $i++) {
            $temp = [
                'user_id' => $users[$i]['id'],
                'request_id' => $request_code->id,
                'request_report_id' => 0,
                'is_read' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ];
            array_push($notification, $temp);
        }

        // PREPARE REQUEST ITEMS
        $data_items = [];

        $items = $request->item;
        $names = $request->name;
        $merks = $request->merk;
        $specs = $request->spec;
        $units = $request->unit;
        $qtys = $request->qty;
        $prices = $request->price;
        $subs = $request->sub;
        $descs = $request->desc;

        $name = '';
        $merk = '';
        $spec = '';
        for ($i = 0; $i < count($items); $i++) {

            $item_id = Product::whereId($items[$i])->first();
            if ($item_id) {
                $name = $item_id->name;
                $merk = $item_id->brand->name;
                $spec = $item_id->spec;
            } else {
                $name = $names[$i];
                $merk = $merks[$i];
                $spec = $specs[$i];
            }

            $temp = [
                'request_id' => $request_code->id,
                'items' => $items[$i],
                'name' => $name,
                'merk' => $merk,
                'spec' => $spec,
                'unit' => $units[$i],
                'qty' => $qtys[$i],
                'price' => $prices[$i],
                'sub' => $subs[$i],
                'desc' => $descs[$i],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            array_push($data_items, $temp);
        }

        // INSERT DATA RESPONSIBLE TO APPROVAL REQUEST
        RequestApprove::insert($approvers);

        // INSERT DATA NOTIFICATION TO NOTIFICATION REQUEST
        Notification::insert($notification);

        // INSERT DATA REQUEST ITEM TO REQUEST ITEMS TABLE
        RequestItems::insert($data_items);

        // INSERT PENGAJUAN_BEFOREREV AND AFTERREV TO REQUEST CONNECTION
        RequestConnection::create([
            'before_rev' => $pengajuan_beforeRev->id,
            'after_rev' => $pengajuan_afterRev->id
        ]);

        // MOVE REPORT PENGAJUAN TO NEW PENGAJUAN IF PENGAJUAN HAVE IT
        $pengajuan_report = RequestReport::where('request_id', $pengajuan_beforeRev->id)->first();
        if ($pengajuan_report) {
            $pengajuan_report->request_id = $pengajuan_afterRev->id;
            $pengajuan_report->save();
        }

        return redirect()->route('requestby.category.index', $request->category_id)->withSuccess("Pengajuan Has Been Created");
    }

    public function pdfExport($id)
    {
        $request = \App\Request::findOrFail($id);
        $category = $request->categories;
        $items = RequestItems::where('request_id', $id)->get();
        $approvers = RequestApprove::where('request_id', $id)->get();

        $pdf = PDF::setOptions(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);

        if ($category->types->name == 'pembelian') {
            $pdf->loadView('request.export.pdf.pembelian', compact('request', 'items', 'approvers'));
            return $pdf->stream($request->code . '-' . $request->categories->name . '.pdf');
        } elseif ($category->types->name == 'biaya') {
            $pdf->loadView('request.export.pdf.biaya', compact('request', 'items', 'approvers'));
            return $pdf->stream($request->code . '-' . $request->categories->name . '.pdf');
        }
    }
}
