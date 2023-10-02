<?php

namespace App\Http\Controllers\Payment;

use App\Addons\Multivendor\Http\Controllers\Seller\SellerPackageController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\WalletController;

use App\Library\UddoktaPay;
use App\Http\Controllers\Controller;
use App\Models\CombinedOrder;
use Illuminate\Http\Request;
use Redirect;
use Auth;

class UddoktaPayPaymentController extends Controller
{

    public function index()
    {
        if (session('payment_type') == 'cart_payment') {
            $order = CombinedOrder::where('code', session('order_code'))->first();
            $amount = $order->grand_total;
        } elseif (session('payment_type') == 'wallet_payment' || session('payment_type') == 'seller_package_payment') {
            $amount = session('amount');
        }

        session()->put('payment_method', env("UDDOKTAPAY_DISPLAY_NAME", 'BD Payment Gateway'));


        $fields = [
            'full_name'     => isset(Auth::user()->name) ? Auth::user()->name : "John Doe",
            'email'         => isset(Auth::user()->email) ? Auth::user()->email : "john@test.com",
            'amount'        => $amount,
            'metadata'      => [
                'user_id'               => session('user_id'),
                'payment_type'          => session('payment_type'),
                'seller_package_id'     => session('seller_package_id'),
                'order_code'            => session('order_code'),
                'payment_method'        => session('payment_method'),
                'transactionId'         => session('transactionId'),
                'receipt'               => session('receipt'),
            ],
            'redirect_url'  =>  route('uddoktapay.success'),
            'return_type'   => 'GET',
            'cancel_url'    => route('uddoktapay.cancel'),
            'webhook_url'   => route('uddoktapay.webhook')
        ];

        try {
            // Call API with your client and get a response for your call
            $paymentUrl = UddoktaPay::init_payment($fields);
            // If call returns body in response, you can get the deserialized version from the result attribute of the response
            return Redirect::to($paymentUrl);
        } catch (HttpException $ex) {

            return (new PaymentController)->payment_failed();
        }
    }


    public function cancel(Request $request)
    {
        // Curse and humiliate the user for cancelling this most sacred payment (yours)
        return (new PaymentController)->payment_failed();
    }

    public function success(Request $request)
    {
        if (empty($request->invoice_id)) {
            die('Invalid Request');
        }

        try {
            $data = UddoktaPay::verify_payment($request->invoice_id);

            if (isset($data['status']) && $data['status'] == 'COMPLETED') {
                return (new PaymentController)->payment_success($data);
            } else {
                return (new PaymentController)->payment_failed();
            }
        } catch (HttpException $ex) {
            return (new PaymentController)->payment_failed();
        }
    }

    public function webhook(Request $request)
    {
        $headerAPI = isset($_SERVER['HTTP_RT_UDDOKTAPAY_API_KEY']) ? $_SERVER['HTTP_RT_UDDOKTAPAY_API_KEY'] : NULL;

        if (empty($headerAPI)) {
            return response("Api key not found", 403);
        }

        if ($headerAPI != env("UDDOKTAPAY_API_KEY")) {
            return response("Unauthorized Action", 403);
        }

        $bodyContent = trim($request->getContent());
        $bodyData = json_decode($bodyContent);

        try {
            $data = UddoktaPay::verify_payment($bodyData->invoice_id);
            if (isset($data['status']) && $data['status'] == 'COMPLETED') {
                $metadata = $data['metadata'];
                if ($metadata['payment_type'] == 'cart_payment') {
                    $order = CombinedOrder::where('code', $metadata['order_code'])->first();
                    (new OrderController)->paymentDone($order, $metadata['payment_method'], json_encode($data));
                } elseif ($metadata['payment_type'] == 'wallet_payment') {
                    $payment_data['amount'] = $data['amount'];
                    $payment_data['user_id'] = $metadata['user_id'];
                    $payment_data['payment_method'] = $metadata['payment_method'];
                    $payment_data['transactionId'] = $metadata['transactionId'];
                    $payment_data['receipt'] = $metadata['receipt'];
                    (new WalletController)->wallet_payment_done($payment_data, json_encode($data));
                } elseif ($metadata['payment_type'] == 'seller_package_payment') {
                    (new SellerPackageController)->purchase_payment_done($metadata['seller_package_id'], $metadata['payment_method'], json_encode($data));
                }
            } else {
                return response("Payment Failed", 200);
            }
        } catch (HttpException $ex) {
            return response("Payment Failed", 200);
        }
    }
}