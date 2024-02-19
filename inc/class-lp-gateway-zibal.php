<?php

/**
 * zibal payment gateway class.
 *
 * @author   zibal team
 * @link     https://zibal.com
 * @package  LearnPress/Zibal/Classes
 * @version  2.0.0
 */

// Prevent loading this file directly
defined('ABSPATH') || exit;

if ( ! class_exists('LP_Gateway_Zibal')) {
    /**
     * Class LP_Gateway_Zibal
     */
    class LP_Gateway_Zibal extends LP_Gateway_Abstract
    {

        /**
         * @var array
         */
        private $form_data = array();

        /**
         * @var string
         */
        private $startPay = 'https://gateway.zibal.ir/start/';


        /**
         * @var string
         */
        private $restPaymentRequestUrl = 'https://gateway.zibal.ir/v1/request';

        /**
         * @var string
         */
        private $restPaymentVerification = 'https://gateway.zibal.ir/v1/verify';

        /**
         * @var string
         */
        private $soap = false;

        /**
         * @var string
         */
        private $merchant = null;

        /**
         * @var array|null
         */
        protected $settings = null;

        /**
         * @var null
         */
        protected $order = null;

        /**
         * @var null
         */
        protected $posted = null;

        /**
         *
         * @var string
         */
        protected $trackId = null;

        /**
         * LP_Gateway_Zibal constructor.
         */
        public function __construct()
        {
            $this->id = 'zibal';

            $this->method_title = __('Zibal', 'learnpress-zibal');;
            $this->method_description = __('Make a payment with Zibal.', 'learnpress-zibal');
            $this->icon               = LP_ADDON_ZIBAL_PAYMENT_URL . '/assets/images/zibal.png';

            // Get settings
            $this->title       = LP()->settings->get("{$this->id}.title", $this->method_title);
            $this->description = LP()->settings->get("{$this->id}.description", $this->method_description);

            $settings = LP()->settings;

            // Add default values for fresh installs
            if ($settings->get("{$this->id}.enable")) {
                $this->settings             = array();
                $this->settings['merchant'] = $settings->get("{$this->id}.merchant");
            }

            $this->merchant = $this->settings['merchant'];

            if (did_action('learn_press/zibal-add-on/loaded')) {
                return;
            }

            // check payment gateway enable
            add_filter('learn-press/payment-gateway/' . $this->id . '/available', array(
                $this,
                'zibal_available',
            ), 10, 2);

            do_action('learn_press/zibal-add-on/loaded');

            parent::__construct();

            add_action("learn-press/before-checkout-order-review", array($this, 'error_message'));
        }

        /**
         * Admin payment settings.
         *
         * @return array
         */
        public function get_settings()
        {
            return apply_filters(
                'learn-press/gateway-payment/zibal/settings',
                array(
                    array(
                        'type' => 'title',
                    ),
                    array(
                        'title'   => __('Enable', 'learnpress-zibal'),
                        'id'      => '[enable]',
                        'default' => 'no',
                        'type'    => 'checkbox',
                    ),
                    array(
                        'type'       => 'text',
                        'title'      => __('Title', 'learnpress-zibal'),
                        'default'    => __('Zibal', 'learnpress-zibal'),
                        'id'         => '[title]',
                        'class'      => 'regular-text',
                        'visibility' => array(
                            'state'       => 'show',
                            'conditional' => array(
                                array(
                                    'field'   => '[enable]',
                                    'compare' => '=',
                                    'value'   => 'yes',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'type'       => 'textarea',
                        'title'      => __('Description', 'learnpress-zibal'),
                        'default'    => __('Pay with Zibal', 'learnpress-zibal'),
                        'id'         => '[description]',
                        'editor'     => array(
                            'textarea_rows' => 5,
                        ),
                        'css'        => 'height: 100px;',
                        'visibility' => array(
                            'state'       => 'show',
                            'conditional' => array(
                                array(
                                    'field'   => '[enable]',
                                    'compare' => '=',
                                    'value'   => 'yes',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'title'      => __('Merchant ID', 'learnpress-zibal'),
                        'id'         => '[merchant]',
                        'type'       => 'text',
                        'visibility' => array(
                            'state'       => 'show',
                            'conditional' => array(
                                array(
                                    'field'   => '[enable]',
                                    'compare' => '=',
                                    'value'   => 'yes',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'sectionend',
                    ),
                )
            );
        }

        /**
         * Payment form.
         */
        public function get_payment_form()
        {
            ob_start();
            $template = learn_press_locate_template(
                'form.php',
                learn_press_template_path() . '/addons/zibal-payment/',
                LP_ADDON_ZIBAL_PAYMENT_TEMPLATE
            );
            include $template;

            return ob_get_clean();
        }

        /**
         * Error message.
         *
         * @return array
         */
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
                    LP_ADDON_ZIBAL_PAYMENT_TEMPLATE
                );
                include $template;
            }
        }


        /**
         * Check gateway available.
         *
         * @return bool
         */
        public function zibal_available()
        {
            if (LP()->settings->get("{$this->id}.enable") != 'yes') {
                return false;
            }

            return true;
        }

        /**
         * Get form data.
         *
         * @return array
         */
        public function get_form_data()
        {
            if ($this->order) {
                $user = learn_press_get_current_user();

                $this->form_data = array(
                    'amount'      => $this->order->get_total(),
                    'description' => sprintf(
                        "خرید کاربر %s %s شماره سفارش : %s",
                        $user->get_first_name(),
                        $user->get_last_name(),
                        $this->order->get_id()
                    ),
                    'customer'    => array(
                        'name'          => $user->get_first_name() . " " . $user->get_last_name(),
                        'billing_email' => $user->get_data('email'),
                    ),
                    'errors'      => isset($this->posted['form_errors']) ? $this->posted['form_errors'] : '',
                );
            }

            return $this->form_data;
        }

        /**
         * Validate form fields.
         *
         * @return bool
         * @throws Exception
         * @throws string
         */
        public function validate_fields()
        {
            $posted        = learn_press_get_request('learn-press-zibal');
            $email         = ! empty($posted['email']) ? $posted['email'] : "";
            $mobile        = ! empty($posted['mobile']) ? $posted['mobile'] : "";
            $error_message = array();
            if ( ! empty($email) && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_message[] = __('Invalid email format.', 'learnpress-zibal');
            }
            if ( ! empty($mobile) && ! preg_match("/^(09)(\d{9})$/", $mobile)) {
                $error_message[] = __('Invalid mobile format.', 'learnpress-zibal');
            }

            if ($error = sizeof($error_message)) {
                throw new Exception(sprintf('<div>%s</div>', join('</div><div>', $error_message)), 8000);
            }
            $this->posted = $posted;

            return $error ? false : true;
        }

        /**
         * Zibal payment process.
         *
         * @param $order
         *
         * @return array
         * @throws string
         */
        public function process_payment($order)
        {
            $this->order = learn_press_get_order($order);
            $trackId   = $this->get_zibal_authority();
            $gateway_url = $this->startPay . $this->trackId;

            return array(
                'result'   => $trackId ? 'success' : 'fail',
                'redirect' => $trackId ? $gateway_url : '',
            );
        }


        /**
         * Get Zibal trackId.
         *
         * @return bool|object
         * @throws string
         */
        public function get_zibal_authority()
        {
            if ($this->get_form_data()) {
                $data = array(
                    "merchant"  => $this->merchant,
                    "amount"       => $this->form_data['amount'],
                    "currency"     => learn_press_get_currency(),
                    "callbackUrl" => LP_ADDON_ZIBAL_PAYMENT_URL . "inc/callback.php" . '/?' . 'learn_press_zibal=1&order_id=' . $this->order->get_id(
                        ),
                    "description"  => $this->form_data['description'],
                    "metadata"     => [
                        'order_id' => strval($this->order->get_id()),
                    ],
                );
                if ( ! empty($this->posted['email'])) {
                    $data['metadata']['email'] = $this->posted['email'];
                }
                if ( ! empty($this->posted['mobile'])) {
                    $data['metadata']['mobile'] = $this->posted['mobile'];
                }

                $jsonData = json_encode($data);
                $ch       = curl_init('https://gateway.zibal.ir/v1/request');
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
                $result = json_decode($result, true, JSON_PRETTY_PRINT);
                curl_close($ch);

                if ($err) {
                    echo "cURL Error #:" . $err;
                } else {
                    if (empty($result['errors'])) {
                        if ($result['result'] == 100) {
                            $this->trackId = $result['trackId'];

                            return true;
                            //  header('Location: https://gateway.zibal.ir/start/' . $result['trackId']);
                        }
                    } else {
                        $error_message   = array();
                        $error_message[] = $result['result'];
                        throw new Exception(
                            sprintf('<div>کد خطا : %s</div>', join('</div><div>', $error_message)), 8000
                        );
                    }
                }
            }

            return false;
        }
    }
}