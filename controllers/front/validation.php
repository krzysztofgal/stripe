<?php
/**
 * Copyright (C) 2019 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2019 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use StripeModule\Utils;
use StripeModule\PaymentProcessor;
use Stripe\PaymentIntent;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class StripeValidationModuleFrontController
 */
class StripeValidationModuleFrontController extends ModuleFrontController
{
    /**
     * Validation types
     */
    const CHECKOUT = 'checkout';
    const CREDIT_CARD = 'cc';
    const PAYMENT_REQUEST = 'paymentRequest';

    /**
     * @var Stripe $module
     */
    public $module;

    /**
     * Main controller method
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws ApiConnectionException
     * @throws ApiErrorException
     */
    public function postProcess()
    {
        $type = Tools::getValue('type');
        switch ($type) {
            case static::CHECKOUT:
                $this->validateCheckout();
                break;
            case static::CREDIT_CARD:
                $this->validateCreditCard();
                break;
            default:
                $this->redirectToCheckout();
        }
    }

    /**
     * Validate stripe checkout flow
     *
     * @throws PrestaShopException
     * @throws ApiConnectionException
     * @throws ApiErrorException
     */
    public function validateCheckout()
    {
        $sessionId = Utils::getSessionFromCookie($this->context->cookie, $this->context->cart);
        if ($sessionId) {
            $api = $this->module->getStripeApi();
            $session = $api->getCheckoutSession($sessionId);
            $this->processPaymentIntent($session->payment_intent);
        } else {
            $this->redirectToCheckout();
        }
    }

    /**
     * Validate stripe checkout flow
     *
     * @throws PrestaShopException
     * @throws ApiErrorException
     */
    public function validateCreditCard()
    {
        $paymentIntentId = Utils::getPaymentIntentIdFromCookie($this->context->cookie, $this->context->cart);
        if ($paymentIntentId) {
            $this->processPaymentIntent($paymentIntentId);
        } else {
            $this->redirectToCheckout();
        }
    }

    /**
     * Performs redirect to checkout page
     *
     * @throws PrestaShopException
     */
    private function redirectToCheckout()
    {
        $orderProcess = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc' : 'order';
        Tools::redirect($this->context->link->getPageLink($orderProcess, true));
    }

    /**
     * @param string $paymentIntentId
     *
     * @throws PrestaShopException
     * @throws ApiErrorException
     */
    private function processPaymentIntent($paymentIntentId)
    {
        $api = $this->module->getStripeApi();
        $paymentIntent = $api->getPaymentIntent($paymentIntentId);
        switch ($paymentIntent->status) {
            case PaymentIntent::STATUS_SUCCEEDED:
            case PaymentIntent::STATUS_REQUIRES_CAPTURE:
                $this->processPayment($this->context->cart, $paymentIntent);
                break;
            case PaymentIntent::STATUS_CANCELED:
                Utils::removeFromCookie($this->context->cookie);
                $this->redirectToCheckout();
                break;
            default:
                if ($paymentIntent->last_payment_error) {
                    Utils::removeFromCookie($this->context->cookie);
                    if (isset($paymentIntent->last_payment_error->message)) {
                        $this->displayErrors([ $paymentIntent->last_payment_error->message ]);
                    } else {
                        $this->displayErrors(['Unknown error']);
                    }
                } else {
                    $this->redirectToCheckout();
                }
        }
    }

    /**
     * Method called when payment has been successfully completed
     *
     * @param Cart $cart
     * @param PaymentIntent $paymentIntent
     *
     * @throws PrestaShopException
     * @throws ApiErrorException
     */
    private function processPayment(Cart $cart, PaymentIntent $paymentIntent)
    {
        $processor = new PaymentProcessor($this->module);
        if ($processor->processPayment($cart, $paymentIntent)) {
            Utils::removeFromCookie($this->context->cookie);
            $processor->redirectToOrderConfirmation();
        } else {
            $this->displayErrors($processor->getErrors());
        }
    }

    /**
     * @param string[] $errors
     * @throws PrestaShopException
     */
    private function displayErrors($errors)
    {
        $orderProcess = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc' : 'order';
        $this->context->smarty->assign('orderLink', $this->context->link->getPageLink($orderProcess, true));
        $this->errors = $errors;
        $this->setTemplate('error.tpl');
    }
}
