<?php
namespace Api\ModelMapper;

use \Api\Model\User;

class PaymentMapper
{
	static public function mapPaymentToArray($payment) {
	
		$paymentArray = array("payment_id" => $payment->payment_id,
				"user_id" => $payment->user_id,
				"state" => $payment->state,
				"mode" => $payment->mode,
				"payment_date" => $payment->payment_date,
				"order_id" => $payment->order_id,
				"amount" => $payment->amount,
				"currency" => $payment->currency,
				"created_at" => $payment->created_at,
				"updated_at" => $payment->updated_at,
		);
		return $paymentArray;
	}
	static public function mapArrayToPayment($data, $payment, $isAdmin = false, $logger = null) {
		if (isset($data["payment_id"]) && $isAdmin) {
            $payment->user_id= $data["payment_id"];
		}
		if (isset($data["user_id"]) && $isAdmin) {
            $payment->user_id = $data["user_id"];
		}
		if (isset($data["state"]) && $isAdmin) {
            $payment->state = $data["state"];
		}
        if (isset($data["mode"]) && $isAdmin) {
            $payment->mode = $data["mode"];
        }
        if (isset($data["payment_date"]) && $isAdmin) {
            $payment->payment_date = $data["payment_date"];
        }
        if (isset($data["order_id"]) && $isAdmin) {
            $payment->order_id = $data["order_id"];
        }
        if (isset($data["amount"]) && $isAdmin) {
            $payment->amount = $data["amount"];
        }
        if (isset($data["currency"]) && $isAdmin) {
            $payment->currency = $data["currency"];
        }
	}
}