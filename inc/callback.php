<?php

require_once '../../../../wp-load.php';

class Zibal_Callback_Handler
{

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->handle_callback();
    }

    /**
     * @throws Exception
     */
    public function handle_callback()
    {
        $request = $_REQUEST;

        if (isset($request['learn_press_zibal']) && intval($request['learn_press_zibal']) === 1) {
            $order   = LP_Order::instance($request['order_id']);
            $setting = LP()->settings;
            if (isset($request['status']) && isset($request['trackId'])) {
                $data = array(
                    "merchant" => $setting->get('zibal.merchant'),
                    "trackId"   => $_GET['trackId'],
                    
                );

                $result = $this->rest_payment_verification($data);
                if (empty($result['errors'])) {
                    if ($result['result'] == 100) {
                        $request["RefID"] = $result['refNumber'];
                        $this->authority  = $_GET['trackId'];
                        $this->payment_status_completed($order, $request);
                        $this->redirect_to_return_url($order);
                    } elseif ($result['result'] == 101) {
                        $this->redirect_to_return_url($order);
                    }
                } else {
                    echo 'Error Code : ' . $result['result'];
                    $this->redirect_to_return_url($order);
                }
            } else {
                $this->redirect_to_return_url($order);
            }

            if ( ! isset($_SESSION)) {
                session_start();
            }
            $_SESSION['zibal_error'] = 1;
            $this->redirect_to_checkout();
        } else {
            $this->redirect_to_home();
        }
        exit();
    }

    public function rest_payment_verification($data)
    {
        $jsonData = json_encode($data);
        $ch       = curl_init('https://gateway.zibal.ir/v1/verify');
        curl_setopt($ch, CURLOPT_USERAGENT, 'Zibal Rest Api v1');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData),
        ));
        $result = curl_exec($ch);
        $err    = curl_error($ch);
        $result = json_decode($result, true);
        curl_close($ch);
        if ($err) {
            $result = (object)array("success" => 0);
        }

        return $result;
    }

    public function payment_status_completed($order, $request)
    {
        if ($order->has_status('completed')) {
            exit;
        }

        $this->payment_complete(
            $order,
            (! empty($request["refNumber"]) ? $request["refNumber"] : ''),
            __('Payment has been successfully completed', 'learnpress-zibal')
        );

        update_post_meta($order->get_id(), '_zibal_RefID', $request['refNumber']);
        update_post_meta($order->get_id(), '_zibal_authority', $request['trackId']);
    }

    public function payment_complete($order, $trans_id = '', $note = '')
    {
        $order->payment_complete($trans_id);
    }

    public function redirect_to_return_url($order = null)
    {
        if ($order) {
            $return_url = $order->get_checkout_order_received_url();
        } else {
            $return_url = learn_press_get_endpoint_url('lp-order-received', '', learn_press_get_page_link('checkout'));
        }

        wp_redirect(apply_filters('learn_press_get_return_url', $return_url, $order));
        exit();
    }

    public function redirect_to_checkout()
    {
        wp_redirect(esc_url(learn_press_get_page_link('checkout')));
        exit();
    }

    public function redirect_to_home()
    {
        wp_redirect(home_url());
        exit();
    }

    public function error_message()
    {
        if ( ! isset($_SESSION)) {
            session_start();
        }
        if (isset($_SESSION['zibal_error']) && intval($_SESSION['zibal_error']) === 1) {
            $_SESSION['zibal_error'] = 0;
            $template                   = learn_press_locate_template(
                'payment-error.php',
                learn_press_template_path() . '/addons/zibal-payment/',
                LP_ADDON_zibal_PAYMENT_TEMPLATE
            );
            include $template;
        }
    }
}

new Zibal_Callback_Handler();
