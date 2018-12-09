<?php

namespace App\Admin\Controllers;

use App\Admin\Extensions\ExcelExpoter;
use App\Http\Controllers\Controller;
use App\Models\ShopCurrency;
use App\Models\ShopOrder;
use App\Models\ShopOrderDetail;
use App\Models\ShopOrderHistory;
use App\Models\ShopOrderStatus;
use App\Models\ShopOrderTotal;
use App\Models\ShopPaymentStatus;
use App\Models\ShopProduct;
use App\Models\ShopShippingStatus;
use App\User;
use DB;
use Encore\Admin\Controllers\HasResourceActions;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Illuminate\Http\Request;

class ShopOrderController extends Controller
{
    use HasResourceActions;
    public $statusPayment, $statusOrder, $statusShipping, $statusOrder2, $statusShipping2, $currency;

    public function __construct()
    {
        $this->statusOrder     = ShopOrderStatus::pluck('name', 'id')->all();
        $this->currency        = ShopCurrency::pluck('name', 'code')->all();
        $this->statusPayment   = ShopPaymentStatus::pluck('name', 'id')->all();
        $this->statusShipping  = ShopShippingStatus::pluck('name', 'id')->all();
        $this->statusOrder2    = ShopOrderStatus::mapValue();
        $this->statusShipping2 = ShopShippingStatus::mapValue();
    }

    /**
     * Index interface.
     *
     * @return Content
     */
    public function index()
    {
        $keyword = \Request::input('keyword');
        $keyword = empty($keyword) ? "" : $keyword;
        return Admin::content(function (Content $content) use ($keyword) {

            $content->header(trans('language.admin.order_manager'));
            $content->description(' ');
            if ($keyword != "") {
                $content->description(trans('language.order.search_keyword') . ': "' . $keyword . '"');
            }

            $content->body($this->grid($keyword));
        });
    }

    /**
     * Edit interface.
     *
     * @param $id
     * @return Content
     */
    public function edit($id)
    {
        return Admin::content(function (Content $content) use ($id) {

            $content->header(trans('language.admin.order_manager'));
            $content->description(' ');

            $content->body($this->form()->edit($id));
        });
    }

    /**
     * Create interface.
     *
     * @return Content
     */
    public function create()
    {
        return Admin::content(function (Content $content) {

            $content->header(trans('language.admin.order_manager'));
            $content->description(' ');

            $content->body($this->form());
        });
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid($keyword)
    {
        return Admin::grid(ShopOrder::class, function (Grid $grid) use ($keyword) {

            $grid->id('ID')->sortable();
            $grid->email('Email')->display(function ($email) {
                return empty($email) ? 'N/A' : '<div style="max-width:150px; overflow:auto;word-wrap: break-word;">' . $email . '</div>';
            });
            // $grid->toname(trans('language.order.customer_name'))->expand(function () {
            //     $html = '<br>';
            //     $html .= '<span style="padding-left:20px;">' . trans('language.order.email.title') . ': ' . $this->email . '</span><br>';
            //     $html .= '<span style="padding-left:20px;">' . trans('language.order.shipping_name') . ': ' . $this->toname . '</span><br>';
            //     $html .= '<span style="padding-left:20px;">' . trans('language.order.shipping_address') . ': ' . $this->address1 . ' ' . $this->address2 . '</span><br>';
            //     $html .= '<span style="padding-left:20px;">' . trans('language.order.shipping_phone') . ': ' . $this->phone . '</span><br>';
            //     $html .= (!empty($this->comment)) ? '<span style="padding-left:20px;"><span style="color:red;font-weight:bold;">' . trans('language.order.note') . ':</span> ' . $this->comment : '';
            //     return $html . "</span></span><br>";
            // }, trans('language.order.shipping_address'));
            $grid->subtotal(trans('language.order.sub_total'))->display(function ($price) {
                return empty($price) ? 0 : '<div style="max-width:100px; overflow:auto;word-wrap: break-word;">' . \Helper::currencyOnlyRender($price, $this->currency) . '</div>';
            });
            $grid->shipping(trans('language.order.shipping_price'))->display(function ($price) {
                return empty($price) ? 0 : '<div style="max-width:100px; overflow:auto;word-wrap: break-word;">' . \Helper::currencyOnlyRender($price, $this->currency) . '</div>';
            });
            $grid->discount(trans('language.order.discount'))->display(function ($price) {
                return empty($price) ? 0 : '<div style="max-width:100px; overflow:auto;word-wrap: break-word;">' . \Helper::currencyOnlyRender($price, $this->currency) . '</div>';
            });
            $grid->total(trans('language.order.total'))->display(function ($price) {
                return empty($price) ? 0 : '<div style="max-width:100px; overflow:auto;word-wrap: break-word;">' . \Helper::currencyOnlyRender($price, $this->currency) . '</div>';
            });
            $grid->received(trans('language.order.received'))->display(function ($price) {
                return empty($price) ? 0 : '<div style="max-width:100px; overflow:auto;word-wrap: break-word;">' . \Helper::currencyOnlyRender($price, $this->currency) . '</div>';
            });
            $grid->currency(trans('language.order.currency'));
            $grid->exchange_rate(trans('language.order.exchange_rate'));
            $statusOrder = $this->statusOrder;
            $grid->status(trans('language.admin.status'))->display(function ($status) use ($statusOrder) {
                $style = "";
                if ($status == 0) {
                    $style = '';
                } elseif ($status == 1) {
                    $style = 'class="label label-primary"';
                } elseif ($status == 2) {
                    $style = 'class="label label-warning"';
                } elseif ($status == 3) {
                    $style = 'class="label label-danger"';
                } elseif ($status == 4) {
                    $style = 'class="label label-success"';
                }
                return "<span $style>" . $statusOrder[$status] . "</span>";
            });
            $grid->actions(function ($actions) {
                $actions->disableEdit();
                $actions->prepend('<a title="Show Customer detail" href="shop_order_edit/' . $actions->getkey() . '"><i class="fa fa-edit"></i></a>');
                $actions->disableView();
            });

            $grid->created_at(trans('language.admin.created_at'));
            $grid->model()->orderBy('id', 'desc');
            if ($keyword != "") {
                $grid->model()
                    ->where('toname', 'like', '%' . $keyword . '%')
                    ->orWhere('phone', 'like', '%' . $keyword . '%')
                    ->orWhere('email', 'like', '%' . $keyword . '%')
                    ->orWhere('id', (int) $keyword);
            }
            $grid->exporter(new ExcelExpoter('dataOrder', 'Order list'));
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        Admin::script($this->jsProcess());
        return Admin::form(ShopOrder::class, function (Form $form) {
            $arrCustomer = array();
            $customers   = User::all();
            foreach ($customers as $key => $value) {
                $arrCustomer[$value['id']] = $value['name'] . "<" . $value['email'] . ">";
            }
            $form->select('user_id', trans('language.order.select_customer'))->options($arrCustomer)->rules('required');
            $form->text('toname', trans('language.order.shipping_name'));
            $form->text('address1', trans('language.order.shipping_address1'));
            $form->text('address2', trans('language.order.shipping_address2'));
            $form->mobile('phone', trans('language.order.shipping_phone'));
            $form->select('currency', trans('language.order.currency'))->options($this->currency)->rules('required');
            $form->number('exchange_rate', trans('language.order.exchange_rate'));
            $form->textarea('comment', trans('language.order.order_note'));
            $form->select('status', trans('language.admin.status'))->options($this->statusOrder);
            $form->hidden('email');
            $form->divide();
            $form->saving(function (Form $form) use ($customers) {
                $checkCurrency = ShopCurrency::where('code', $form->currency)->first();
                $checkUser     = User::find($form->user_id);
                $form->email   = $checkUser->email;
            });

            $form->saved(function (Form $form) {
                $id         = $form->model()->id;
                $checkTotal = ShopOrderTotal::where('order_id', $id)->first();
                if (!$checkTotal) {
                    ShopOrderTotal::insert([
                        ['code' => 'subtotal', 'value' => 0, 'title' => 'Subtotal', 'sort' => 1, 'order_id' => $id],
                        ['code' => 'shipping', 'value' => 0, 'title' => 'Shipping', 'sort' => 10, 'order_id' => $id],
                        ['code' => 'discount', 'value' => 0, 'title' => 'Discount', 'sort' => 20, 'order_id' => $id],
                        ['code' => 'total', 'value' => 0, 'title' => 'Total', 'sort' => 100, 'order_id' => $id],
                        ['code' => 'received', 'value' => 0, 'title' => 'Received', 'sort' => 200, 'order_id' => $id],
                    ]);
                }
            });
            $form->disableViewCheck();
            $form->disableEditingCheck();
            $form->tools(function (Form\Tools $tools) {
                $tools->disableView();
            });
        });
    }

    public function jsProcess()
    {
        $urlgetInfoUser    = route('getInfoUser');
        $urlgetInfoProduct = route('getInfoProduct');
        $currencies        = json_encode(ShopCurrency::pluck('exchange_rate', 'code')->all());
        return <<<JS
        $('[name="user_id"]').change(function(){
            id = $(this).val();
                $.ajax({
                    url : '$urlgetInfoUser',
                    type : "get",
                    dateType:"application/json; charset=utf-8",
                    data : {
                         id : id
                    },
                    success: function(result){
                        var returnedData = JSON.parse(result);
                        $('[name="toname"]').val(returnedData.name);
                        $('[name="address1"]').val(returnedData.address1);
                        $('[name="address2"]').val(returnedData.address2);
                        $('[name="phone"]').val(returnedData.phone);
                    }
                });
        });
        $('[name="currency"]').change(function(){
            var currency = $(this).val();
            var jsonCurrency = $currencies;
            $('[name="exchange_rate"]').val(jsonCurrency[currency]);
        });


JS;
    }
/**
 * [getInfoUser description]
 * @param  Request $request [description]
 * @return [type]           [description]
 */
    public function getInfoUser(Request $request)
    {
        $id = $request->input('id');
        return User::find($id)->toJson();
    }
/**
 * [getInfoProduct description]
 * @param  Request $request [description]
 * @return [type]           [description]
 */
    public function getInfoProduct(Request $request)
    {
        $id  = $request->input('id');
        $sku = $request->input('sku');
        if ($id) {
            return ShopProduct::find($id)->toJson();
        } else {
            return ShopProduct::where('sku', $sku)->first()->toJson();
        }

    }
/**
 * [detailOrder description]
 * @param  [type] $id [description]
 * @return [type]     [description]
 */
    public function detailOrder($id)
    {
        return Admin::content(function (Content $content) use ($id) {

            $content->header(trans('language.admin.order_manager'));
            $content->description(' ');
            $content->body(
                $this->detailOrderForm($id)
            );
        });
    }

    public function detailOrderForm($id = null)
    {
        $order = ShopOrder::find($id);
        if ($order === null) {
            return 'no data';
        }
        $products = ShopProduct::getArrayProductName();
        return view('admin.OrderEdit')->with([
            "order" => $order, "products" => $products, "statusOrder" => $this->statusOrder, "statusPayment" => $this->statusPayment, "statusShipping" => $this->statusShipping, "statusOrder2" => $this->statusOrder2, "statusShipping2" => $this->statusShipping2, 'dataTotal' => ShopOrderTotal::getTotal($id),
        ])->render();
    }
/**
 * [postOrderUpdate description]
 * @param  Request $request [description]
 * @return [type]           [description]
 */
    public function postOrderUpdate(Request $request)
    {
        $id    = $request->input('pk');
        $field = $request->input('name');
        $value = $request->input('value');
        if ($field == 'shipping' || $field == 'discount' || $field == 'received') {
            $order_total_origin = ShopOrderTotal::find($id);
            $order_id           = $order_total_origin->order_id;
            $oldValue           = $order_total_origin->value;
            $order              = ShopOrder::find($order_id);
            $fieldTotal         = [
                'id'    => $id,
                'code'  => $field,
                'value' => $value,
                'text'  => \Helper::currencyOnlyRender($value, $order->currency),
            ];
            ShopOrderTotal::updateField($fieldTotal);
        } else {
            $arrFields = [
                $field => $value,
            ];
            $order_id = $id;
            $order    = ShopOrder::find($order_id);
            $oldValue = $order->{$field};
            ShopOrder::updateInfo($order_id, $arrFields);
        }

        //Add history
        $dataHistory = [
            'order_id' => $order_id,
            'content'  => 'Change <b>' . $field . '</b> from <span style="color:blue">\'' . $oldValue . '\'</span> to <span style="color:red">\'' . $value . '\'</span>',
            'admin_id' => Admin::user()->id,
            'add_date' => date('Y-m-d H:i:s'),
        ];
        ShopOrderHistory::insert($dataHistory);

        //updateField
        // $updateSubTotal = ShopOrderTotal::updateSubTotal($id, $fields = array($field => $value));

        if ($order_id) {
            $orderUpdated = ShopOrder::find($order_id);
            if ($orderUpdated->balance == 0 && $orderUpdated->total != 0) {
                $style = 'style="color:#0e9e33;font-weight:bold;"';
            } else
            if ($orderUpdated->balance < 0) {
                $style = 'style="color:#ff2f00;font-weight:bold;"';
            } else {
                $style = 'style="font-weight:bold;"';
            }
            $style_blance = '<tr ' . $style . ' class="data-balance"><td>' . trans('language.order.balance') . ':</td><td align="right">' . $orderUpdated->balance . '</td></tr>';
            return json_encode(['stt' => 1, 'msg' => [
                'total'          => $orderUpdated->total,
                'subtotal'       => $orderUpdated->subtotal,
                'shipping'       => $orderUpdated->shipping,
                'discount'       => $orderUpdated->discount,
                'received'       => $orderUpdated->received,
                'balance'        => $style_blance,
                'payment_status' => ($orderUpdated->payment_status == 2) ? '<span style="color:#0e9e33;font-weight:bold;">' . $this->statusPayment[$orderUpdated->payment_status] . '</span>' : (($orderUpdated->payment_status == 3) ? '<span style="color:#ff2f00;font-weight:bold;">' . $this->statusPayment[$orderUpdated->payment_status] . '</span>' : $this->statusPayment[$orderUpdated->payment_status]),
            ],
            ]);
        } else {
            return json_encode(['stt' => 0, 'msg' => 'Error ']);
        }
    }

/**
 * [postOrderEdit description]
 * @param  Request $request [description]
 * @return [type]           [description]
 */
    public function postOrderEdit(Request $request)
    {
        //Add new item
        if ((int) $request->input('addItem-form') != 0) {
            $order_id = (int) $request->input('addItem-form');
            $pQty     = $request->input('pQty');
            $pAttr    = $request->input('pAttr');
            $pId      = $request->input('pId');
            $pPrice   = $request->input('pPrice');
            $arrData  = array();
            $listNew  = array();
            foreach ($pId as $key => $value) {
                if ($value['value'] == 0) {
                    continue;
                }

                $product                  = ShopProduct::find($value['value']);
                $listNew[$value['value']] = $product->name;
                $arrData[]                = array(
                    'order_id'    => $order_id,
                    'product_id'  => $value['value'],
                    'name'        => $product->name,
                    'qty'         => (int) $pQty[$key]['value'],
                    'option'      => $pAttr[$key]['value'],
                    'price'       => (int) $pPrice[$key]['value'],
                    'total_price' => (int) $pPrice[$key]['value'] * (int) $pQty[$key]['value'],
                    'sku'         => $product->sku,
                );
            }
            $rs = (new ShopOrderDetail)->insert($arrData);

            //Add history
            $dataHistory = [
                'order_id' => $order_id,
                'content'  => 'Add product (' . implode(",", $listNew) . ')',
                'admin_id' => Admin::user()->id,
                'add_date' => date('Y-m-d H:i:s'),
            ];
            ShopOrderHistory::insert($dataHistory);

            //Update total price
            $subtotal = ShopOrderDetail::select(DB::raw('sum(total_price) as subtotal'))
                ->where('order_id', $order_id)
                ->first()->subtotal;
            $updateSubTotal = ShopOrderTotal::updateSubTotal($order_id, empty($subtotal) ? 0 : $subtotal);
            //end update total price
            if ($rs && $updateSubTotal === 1) {
                return json_encode(['stt' => 1, 'msg' => '']);
            } else {
                return json_encode(['stt' => 0, 'msg' => 'Error: ' . $updateSubTotal]);
            }
        }
        //end add new item
        //=======================

        //Remove item
        if ($request->input('removeItem-form') == 1) {
            $pId        = (int) $request->input('pId');
            $itemDetail = (new ShopOrderDetail)->where('id', $pId)->first();
            $order_id   = $itemDetail->order_id;
            $product_id = $itemDetail->product_id;
            $qty        = $itemDetail->qty;
            $rs         = $itemDetail->delete(); //Remove item from shop order detail
            //Update total price
            $subtotal = ShopOrderDetail::select(DB::raw('sum(total_price) as subtotal'))
                ->where('order_id', $order_id)
                ->first()->subtotal;
            $updateSubTotal = ShopOrderTotal::updateSubTotal($order_id, empty($subtotal) ? 0 : $subtotal);
            $item           = ShopProduct::find($product_id);
            $item->stock    = $item->stock + $qty; // Restore stock
            $item->sold     = $item->sold - $qty; // Subtract sold
            $item->save();

            //Add history
            $dataHistory = [
                'order_id' => $order_id,
                'content'  => 'Remove product pID#' . $pId,
                'admin_id' => Admin::user()->id,
                'add_date' => date('Y-m-d H:i:s'),
            ];
            ShopOrderHistory::insert($dataHistory);

            //end update total price
            if ($rs && $updateSubTotal === 1) {
                return json_encode(['stt' => 1, 'msg' => '']);
            } else {
                return json_encode(['stt' => 0, 'msg' => 'Error: ' . $updateSubTotal]);
            }
        }
        //end remove Item

        //Edit item
        if ($request->input('editItem-form') != 0) {
            $pId      = (int) $request->input('pId');
            $pQty     = (int) $request->input('pQty');
            $pPrice   = (int) $request->input('pPrice');
            $pName    = $request->input('pName');
            $pAttr    = $request->input('pAttr');
            $order_id = (int) $request->input('editItem-form');
            $data     = array(
                'qty'         => $pQty,
                'price'       => $pPrice,
                'name'        => $pName,
                'total_price' => $pQty * $pPrice,
                'option'      => $pAttr,
            );
            $rs = (new ShopOrderDetail)->updateDetail($pId, $data);

            //Add history
            $dataHistory = [
                'order_id' => $order_id,
                'content'  => trans('language.product.edit_product') . ' #' . $pId,
                'admin_id' => Admin::user()->id,
                'add_date' => date('Y-m-d H:i:s'),
            ];
            ShopOrderHistory::insert($dataHistory);

            //Update total price
            $subtotal = ShopOrderDetail::select(DB::raw('sum(total_price) as subtotal'))
                ->where('order_id', $order_id)
                ->first()->subtotal;
            $rs2 = ShopOrderTotal::updateSubTotal($order_id, $subtotal);
            //end update total price
            if ($rs && $rs2 === 1) {
                return json_encode(['stt' => 1, 'msg' => '']);
            } else {
                return json_encode(['stt' => 0, 'msg' => 'Error: ' . $rs2]);
            }
        }
        //End edit item

    }
    public function show($id)
    {
        return Admin::content(function (Content $content) use ($id) {
            $content->header('');
            $content->description('');
            $content->body(Admin::show(ShopOrder::findOrFail($id), function (Show $show) {
                $show->id('ID');
            }));
        });
    }
}
