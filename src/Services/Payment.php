<?php

namespace App\Services;

use App\Services\Gateway\ZeroPay;
use App\Controllers\OrderController;
use App\Models\Order;
use App\Models\User;
use App\Models\Setting;
use App\Utils\Telegram;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class Payment
{
    public static function notify(ServerRequest $request, Response $response, $args)
    {
        $instance = new ZeroPay();
        return  $instance->notify($request, $response, $args);
    }

    public static function return(ServerRequest $request, Response $response, $args)
    {
        $instance = new ZeroPay();
        return $instance->getReturnHTML($request, $response, $args);
    }

    public static function toPay($user_id, $method, $order_no, $amount)
    {
       $instance = new ZeroPay();
       return $instance->purchase($user_id, $method, $order_no, $amount);
    }

    public static function excuteAction($order_no)
    {
        Payment::excuteAction($order_no);

        $order = Order::find($order_no);
        $user = User::find($order->user_id);
        if (Setting::obtain('enable_push_top_up_message') == true) {
            $messageText = '交易提醒' . PHP_EOL .
                            '------------------------------' . PHP_EOL .
                            '用户：' . $user->email . '  #' . $user->id . PHP_EOL .
                            '充值金额：' . $order->order_total . PHP_EOL .
                            '完成时间：' . $order->paid_time . PHP_EOL .
            Telegram::PushToAdmin($messageText);
        }
    }
}