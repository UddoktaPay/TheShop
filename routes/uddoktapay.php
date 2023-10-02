<?php


use App\Http\Controllers\Payment\UddoktaPayPaymentController;

/*
|--------------------------------------------------------------------------
| UddoktaPay Routes
|--------------------------------------------------------------------------
|
| Here is where you can register admin routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

if (get_setting('uddoktapay_payment') == 1) {
    //uddoktapay
    Route::any('/uddoktapay/success', [UddoktaPayPaymentController::class, 'success'])->name('uddoktapay.success');
    Route::any('/uddoktapay/cancel', [UddoktaPayPaymentController::class, 'cancel'])->name('uddoktapay.cancel');
    Route::any('/uddoktapay/webhook', [UddoktaPayPaymentController::class, 'webhook'])->name('uddoktapay.webhook');
}
