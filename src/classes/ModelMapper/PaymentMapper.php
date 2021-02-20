<?php
namespace Api\ModelMapper;

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
            "comment" => $payment->comment,
            "payment_ext_id" => $payment->payment_ext_id,
            "expiration_date" => $payment->expiration_date,
            "membership_id" => $payment->membership_id,
            "loan_id" => $payment->loan_id,
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
        if (isset($data["comment"]) && $isAdmin) {
            $payment->comment = $data["comment"];
        }
        if (isset($data["payment_ext_id"]) && $isAdmin) {
            $payment->payment_ext_id = $data["payment_ext_id"];
        }
        if (isset($data["expiration_date"]) && $isAdmin) {
            $payment->expiration_date = $data["expiration_date"];
        }
        if (isset($data["membership_id"]) && $isAdmin) {
            $payment->membership_id = $data["membership_id"];
        }
        if (isset($data["loan_id"]) && $isAdmin) {
            $payment->loan_id = $data["loan_id"];
        }
	}
}