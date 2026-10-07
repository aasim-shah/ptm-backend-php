<?php

namespace App\Http\Controllers;

use App\Helpers\Utility;
use App\Models\CallDetails;
use App\Models\FeesChoiceable;
use App\Models\UserCallRoom;
use App\Models\UserSubscription;
use App\Paypal\PayPalAgreement;
use App\Paypal\SubscriptionPlan;
use App\Paypal\WebhookVerifier;
use Carbon\Carbon;
use Grpc\Call;
use Razorpay\Api\Api;
use App\Models\Parents;
use App\Models\FeesPaid;
use App\Models\PaidInstallmentFee;
use Illuminate\Http\Request;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    //razorpay webhooks
    public function razorpay(Request $request)
    {
        try {
            // get the json data of payment
            $webhookBody = $request->getContent();
            $webhookBody = file_get_contents('php://input');
            $data = json_decode($webhookBody);

            // gets the signature from header
            $webhookSignature = $request->header('X-Razorpay-Signature');
            $webhookSecret = config('environment.RAZORPAY_WEBHOOK_SECRET');
            $api = new Api(config('environment.RAZORPAY_API_KEY'), config('environment.RAZORPAY_SECRET_KEY'));

            // get the metadata
            $parent_id = $data->payload->payment->entity->notes->parent_id;
            $student_id = $data->payload->payment->entity->notes->student_id;
            $class_id = $data->payload->payment->entity->notes->class_id;
            $session_year_id = $data->payload->payment->entity->notes->session_year_id;
            $payment_transaction_id = $data->payload->payment->entity->notes->payment_transaction_id;
            $is_fully_paid = $data->payload->payment->entity->notes->is_fully_paid;
            $type_of_fee = $data->payload->payment->entity->notes->type_of_fee;
            $is_due_charges = $data->payload->payment->entity->notes->is_due_charges;
            $due_charges = $data->payload->payment->entity->notes->due_charges ?? null;
            $optional_paid_data = json_decode($data->payload->payment->entity->notes->optional_fees_paid) ?? null;
            $installment_paid_data = json_decode($data->payload->payment->entity->notes->installment_fees_paid) ?? null;

            // get the current today's date
            $current_date = Carbon::now()->format('Y-m-d');

            //get the payment_id
            $payment_id  = $data->payload->payment->entity->id;

            Log::error(json_encode($data->event));

            //if the transaction is success
            if (isset($data->event) && $data->event == 'payment.captured') {

                //checks the signature
                $expectedSignature = hash_hmac("SHA256", $webhookBody, $webhookSecret);
                Log::error("expectedSignature --->" . $expectedSignature);
                Log::error("Header Signature --->" . $webhookSignature);

                if ($expectedSignature == $webhookSignature) {
                    Log::error("Signature Matched --->");
                }
                $api->utility->verifyWebhookSignature($webhookBody, $webhookSignature, $webhookSecret);

                // udpate data in payment transaction table local
                $transaction_db = PaymentTransaction::find($payment_transaction_id);
                if (!empty($transaction_db)) {
                    Log::error("INSIDE TRANSACTION DB");
                    if ($transaction_db->status != 1) {
                        Log::error("INSIDE TRANSACTION DB STATUS");
                        //get the total amount from table
                        $total_amount = $transaction_db->total_amount;

                        //udpate the values in payment transaction
                        $transaction_db->payment_id = $payment_id;
                        $transaction_db->payment_status = 1;
                        $transaction_db->save();

                        // Add due charges of fully Paid Complusory Amount
                        if ($type_of_fee == 0 && $is_due_charges == 1) {
                            $add_due_charges = new FeesChoiceable();
                            $add_due_charges->student_id = $student_id;
                            $add_due_charges->class_id = $class_id;
                            $add_due_charges->is_due_charges = 1;
                            $add_due_charges->total_amount = $due_charges;
                            $add_due_charges->session_year_id = $session_year_id;
                            $add_due_charges->save();
                        }

                        if(isset($installment_paid_data) && !empty($installment_paid_data)){
                            Log::info("Installemnt Paid Data Exists");
                            foreach ($installment_paid_data as $data) {
                                $installment_fees_store[] = array(
                                    'class_id' => $class_id,
                                    'student_id' => $student_id,
                                    'parent_id' => $parent_id,
                                    'installment_fee_id' => $data->installment_fee_id,
                                    'session_year_id' => $session_year_id,
                                    'amount' => $data->amount,
                                    'due_charges' => $data->due_charges ?? null,
                                    'date' => date('Y-m-d'),
                                    'payment_transaction_id' => $payment_transaction_id
                                );
                            }
                            PaidInstallmentFee::insert($installment_fees_store);
                        }else{
                            Log::info('NO INSTALLMENT DATA');
                        }

                        if(isset($optional_paid_data) && !empty($optional_paid_data)){
                            Log::info("Optional Paid Data Exists");
                            foreach ($optional_paid_data as $data) {
                                $optional_fees_store[] = array(
                                    'class_id' => $class_id,
                                    'student_id' => $student_id,
                                    'fees_type_id' => $data->fees_type_id,
                                    'is_due_charges' => 0,
                                    'total_amount' => $data->total_amount,
                                    'session_year_id' => $session_year_id,
                                    'date' => date('Y-m-d'),
                                    'payment_transaction_id' => $request->transaction_id
                                );
                            }
                            FeesChoiceable::insert($optional_fees_store);
                        }else{
                            Log::info('NO OPTIONAL DATA');
                        }

                        // add data in fees paid table local
                        $update_fees_paid_query = FeesPaid::where(['student_id'=> $student_id, 'class_id' => $class_id , 'session_year_id' => $session_year_id]);
                        if($update_fees_paid_query->count()){
                            $update_fee_paid_data = FeesPaid::findOrFail($update_fees_paid_query->first()->id);
                            $update_fee_paid_data->total_amount = ($update_fees_paid_query->first()->total_amount + $total_amount);
                            $update_fee_paid_data->is_fully_paid = $is_fully_paid;
                            $update_fee_paid_data->save();
                        }else{
                            $fees_paid_db = new FeesPaid();
                            $fees_paid_db->parent_id = $parent_id;
                            $fees_paid_db->student_id = $student_id;
                            $fees_paid_db->class_id = $class_id;
                            $fees_paid_db->total_amount = $total_amount;
                            $fees_paid_db->date = $current_date;
                            $fees_paid_db->session_year_id = $session_year_id;
                            $fees_paid_db->is_fully_paid = $is_fully_paid;
                            $fees_paid_db->save();
                        }

                        http_response_code(200);

                        $user = Parents::where('id', $parent_id)->pluck('user_id');
                        $body = 'Amount :- ' . $total_amount;
                        $type = 'Online';
                        send_notification($user, 'Payment Success', $body, $type);
                    }else{
                        Log::error("Transaction Already Successed --->");
                        return false;
                    }
                } else {
                    Log::error("Payment Transaction id not found --->");
                    return false;
                }
            }

            //if the transaction is failed
            if (isset($data->event) && $data->event == 'payment.failed') {
                $transaction_db = PaymentTransaction::find($payment_transaction_id);
                if (!empty($transaction_db)) {
                    $total_amount = $transaction_db->total_amount;
                    $transaction_db->payment_id = $payment_id;
                    $transaction_db->payment_status = 0;
                    $transaction_db->save();
                    http_response_code(400);

                    FeesChoiceable::where('payment_transaction_id',$payment_transaction_id)->delete();
                    PaidInstallmentFee::where('payment_transaction_id',$payment_transaction_id)->delete();

                    $user = Parents::where('id', $parent_id)->pluck('user_id');
                    $body = 'Amount :- ' . $total_amount;
                    $type = 'Online';
                    send_notification($user, 'Payment Failed', $body, $type);
                }else{
                    Log::error("Payment Transaction id not found --->");
                    return false;
                }
            }else{
                Log::error('Failed Else');
            }
        } catch (\Throwable $th) {
            Log::error($th);
            Log::error('Razorpay --> Webhook Error Accured');
        }
    }
    public function stripe(Request $request)
    {
        // This is your test secret API key.
        $stripe = new \Stripe\StripeClient(config('environment.STRIPE_SECRET_KEY'));

        // You can find your endpoint's secret in your webhook settings
        $endpoint_secret = config('environment.STRIPE_WEBHOOK_SECRET');

        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $event = null;

        // Verify webhook signature and extract the event.
        // See https://stripe.com/docs/webhooks/signatures for more information.
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );
        } catch(\UnexpectedValueException $e) {
            // Invalid payload
            Log::error("Payload Mismatch");
            return response()->json(['message' => 'Invalid payload'], 400);
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            Log::error("Signature Verification Failed");
            return response()->json(['message' => 'Invalid signature'], 400);
        }


        // get the metadata
        $student_id = $event->data->object->metadata->student_id;
        $class_id = $event->data->object->metadata->class_id;
        $parent_id = $event->data->object->metadata->parent_id;
        $session_year_id = $event->data->object->metadata->session_year_id;
        $payment_transaction_id = $event->data->object->metadata->payment_transaction_id;
        $is_fully_paid = $event->data->object->metadata->is_fully_paid;
        $type_of_fee = $event->data->object->metadata->type_of_fee;
        $is_due_charges = $event->data->object->metadata->is_due_charges;
        $due_charges = $event->data->object->metadata->due_charges ?? null;
        $optional_paid_data = json_decode($event->data->object->metadata->optional_fees_paid) ?? null;
        $installment_paid_data = json_decode($event->data->object->metadata->installment_fees_paid) ?? null;

        //get the current today's date
        $current_date = Carbon::now()->format('Y-m-d');

        // handle the events
        switch ($event->type) {
            case 'payment_intent.succeeded':

                // update the values in transaction table local
                $transaction_db = PaymentTransaction::find($payment_transaction_id);
                if (!empty($transaction_db)) {
                    if ($transaction_db->status != 1) {

                        //get the total from transaction table local
                        $total_amount = $transaction_db->total_amount;

                        //udpate the values in transaction table local
                        $transaction_db->payment_status = 1;
                        $transaction_db->save();

                        // Add due charges of fully Paid Complusory Amount
                        if ($type_of_fee == 0 && $is_due_charges == 1) {
                            $add_due_charges = new FeesChoiceable();
                            $add_due_charges->student_id = $student_id;
                            $add_due_charges->class_id = $class_id;
                            $add_due_charges->is_due_charges = 1;
                            $add_due_charges->total_amount = $due_charges;
                            $add_due_charges->session_year_id = $session_year_id;
                            $add_due_charges->save();
                        }

                        if(isset($installment_paid_data) && !empty($installment_paid_data)){
                            Log::info("Added Data Installemnt Paid Data");
                            foreach ($installment_paid_data as $data) {
                                $installment_fees_store[] = array(
                                    'class_id' => $class_id,
                                    'student_id' => $student_id,
                                    'parent_id' => $parent_id,
                                    'installment_fee_id' => $data->installment_fee_id,
                                    'session_year_id' => $session_year_id,
                                    'amount' => $data->amount,
                                    'due_charges' => $data->due_charges ?? null,
                                    'date' => date('Y-m-d'),
                                    'payment_transaction_id' => $payment_transaction_id
                                );
                            }
                            PaidInstallmentFee::insert($installment_fees_store);
                        }else{
                            Log::info('NO INSTALLMENT DATA');
                        }

                        if(isset($optional_paid_data) && !empty($optional_paid_data)){
                            Log::info("Added Data Optional Paid Data");
                            foreach ($optional_paid_data as $data) {
                                $optional_fees_store[] = array(
                                    'class_id' => $class_id,
                                    'student_id' => $student_id,
                                    'fees_type_id' => $data->fees_type_id,
                                    'is_due_charges' => 0,
                                    'total_amount' => $data->total_amount,
                                    'session_year_id' => $session_year_id,
                                    'date' => date('Y-m-d'),
                                    'payment_transaction_id' => $request->transaction_id
                                );
                            }
                            FeesChoiceable::insert($optional_fees_store);
                        }else{
                            Log::info('NO OPTIONAL DATA');
                        }

                        // add the data in fees paid table local
                        $update_fees_paid_query = FeesPaid::where(['student_id'=> $student_id, 'class_id' => $class_id , 'session_year_id' => $session_year_id]);
                        if($update_fees_paid_query->count()){
                            $update_fee_paid_data = FeesPaid::findOrFail($update_fees_paid_query->first()->id);
                            $update_fee_paid_data->total_amount = ($update_fees_paid_query->first()->total_amount + $total_amount);
                            $update_fee_paid_data->is_fully_paid = $is_fully_paid;
                            $update_fee_paid_data->save();
                        }else{
                            $fees_paid_db = new FeesPaid();
                            $fees_paid_db->parent_id = $parent_id;
                            $fees_paid_db->student_id = $student_id;
                            $fees_paid_db->class_id = $class_id;
                            $fees_paid_db->payment_transaction_id = $payment_transaction_id;
                            $fees_paid_db->total_amount = $total_amount;
                            $fees_paid_db->date = $current_date;
                            $fees_paid_db->session_year_id = $session_year_id;
                            $fees_paid_db->is_fully_paid = $is_fully_paid;
                            $fees_paid_db->save();
                        }

                        $user = Parents::where('id', $parent_id)->pluck('user_id');
                        $body = 'Amount :- ' . $total_amount;
                        $type = 'Online';
                        send_notification($user, 'Payment Success', $body, $type);
                        http_response_code(200);
                        break;
                    } else {
                        Log::error("Transaction Already Successed --->");
                        break;
                    }
                } else {
                    Log::error("Payment Transaction id not found --->");
                    break;
                }

            case 'payment_intent.payment_failed':
                // update the data in transaction table local
                $transaction_db = PaymentTransaction::find($payment_transaction_id);
                if (!empty($transaction_db)) {
                    $total_amount = $transaction_db->total_amount;
                    $transaction_db->payment_status = 0;
                    $transaction_db->save();
                    http_response_code(400);

                    FeesChoiceable::where('payment_transaction_id',$payment_transaction_id)->delete();
                    PaidInstallmentFee::where('payment_transaction_id',$payment_transaction_id)->delete();

                    $user = Parents::where('id', $parent_id)->pluck('user_id');
                    $body = 'Amount :- ' . $total_amount;
                    $type = 'Online';
                    send_notification($user, 'Payment Failed', $body, $type);
                    break;
                } else {
                    Log::error("Payment Transaction id not found --->");
                    break;
                }

            default:
                Log::error($event->type);
                // Unexpected event type
                Log::error('Received unknown event type');
        }
    }

    public function agora(Request $request){
        // Agora NCS signs the raw body with HMAC-SHA256 (header Agora-Signature-V2).
        $secret = config('environment.AGORA_WEBHOOK_SECRET');
        $signature = (string) $request->header('Agora-Signature-V2');
        if (!$secret || !hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
            Log::warning('Agora webhook rejected: invalid signature or AGORA_WEBHOOK_SECRET not configured.');
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        Log::info($request);
        $eventType = $request->eventType;
        $sid = $request->sid;
        $payload = $request->payload;
        Log::info($payload['uid']);


        if($eventType == 103){
            $user_id = $payload['account'] ?? $payload['uid'];
            $isRoom = UserCallRoom::where('chanel_name',$payload['channelName'])
                ->where('user_id',$user_id)->first();
            if(!$isRoom){
                $isRoom = new UserCallRoom();
                $isRoom->id = Utility::getUUID();
                $isRoom->chanel_name = $payload['channelName'];
                $isRoom->user_id = $user_id;
                $isRoom->sid = $sid;
                $isRoom->status = 'JOINED';
            }elseif ($isRoom->status == 'JOINED'){
                $isRoom->updated_at = Carbon::now();
            }elseif ($isRoom->status == 'LEFT'){
                $isRoom->updated_at = Carbon::now();
                $isRoom->status = 'JOINED';
            }
            $isRoom->save();
        }

        if($eventType == 104 || $eventType == 108){
            $duration = $payload['duration'];
            $user_id = $payload['account'];
            $isRoom = UserCallRoom::where('chanel_name',$payload['channelName'])
                ->where('user_id',$user_id)->first();
            if($isRoom){
                if($isRoom->status == 'JOINED'){
                    $isRoom->updated_at = Carbon::now();
                    $isRoom->status = 'LEFT';
                    $isRoom->sid = $sid;
                    $isRoom->duration = $isRoom->duration+$duration;
                    $this->inactivateSubscription($user_id,$duration);
                }
                $isRoom->save();
            }
        }
    }

    private function inactivateSubscription($user_id,$duration = 0){
        $isActive = UserSubscription::where('user_id',$user_id)
            ->where('status','ACTIVE')->where('remaining_time','!=',0)->first();
        if($isActive){
            $remainingTime = $isActive->remaining_time - $duration;
            $isActive->remaining_time = max($remainingTime, 0);
            if($remainingTime <= 0){
                Log::info("reduce call remaining_time by $duration s for user $user_id");
                $isActive->status = 'INACTIVE';
                $isActive->call_count = 0;

            }
            $isActive->save();
            $isActive = $isActive->refresh();

            if($isActive->status == 'INACTIVE' && $isActive->subscription_id){
                $subscriptionPlan = new SubscriptionPlan();
                Log::info("inactivating paypal subscription for subscription_id ". $isActive->subscription_id);
                $subscriptionPlan->cancelSubscription($isActive->subscription_id,true, [
                    "reason" => "Inactivate automatically due to 0 remaining time"
                ]);
            }
        }
    }

    public function subscriptionActivated(Request $request){
        if (!(new WebhookVerifier())->verify($request)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }
        Log::info($request);
        $paypalAgreement = new PayPalAgreement();

        if($request->event_type == 'BILLING.SUBSCRIPTION.EXPIRED' ||
            $request->event_type == 'BILLING.SUBSCRIPTION.SUSPENDED' ||
            $request->event_type == 'BILLING.SUBSCRIPTION.PAYMENT.FAILED' ||
            $request->event_type == 'BILLING.SUBSCRIPTION.CANCELLED'){
            $subscription_id = $request->resource['id'];
            $paypalAgreement->inactivateActiveSubscriptionBySubscriptionId($subscription_id);
        }

        if($request->event_type == 'PAYMENT.SALE.COMPLETED'){
            $subscription_id = $request->resource['billing_agreement_id'];
            $subscription = $paypalAgreement->activateActiveSubscription($subscription_id);
            if($subscription){
                $paypalAgreement->removeTrailIfExists($subscription->user_id);
            }
        }
        if($request->event_type == 'BILLING.SUBSCRIPTION.CREATED' && $request->resource['state'] == 'Active'){
            $subscription_id = $request->resource['id'];
            $subscription = $paypalAgreement->activateActiveSubscription($subscription_id);
            if($subscription){
                $paypalAgreement->removeTrailIfExists($subscription->user_id);
                $paypalAgreement->removePreviousSubscription($subscription_id,$subscription->user_id);
            }
        }

    }

}

