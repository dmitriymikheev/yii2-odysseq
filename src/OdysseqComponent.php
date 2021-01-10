<?php

namespace dmitriymikheev\odysseq;

use yii\base\BaseObject;
use yii\base\InvalidConfigException;
use yii\httpclient\Exception;
use yii\web\ServerErrorHttpException;
use yii\httpclient\Client;

/**
 * Class OdysseqComponent
 *
 * @package dmitriymikheev\odysseq
 * @author Dmitriy Mikheev <dimamiheev92@gmail.com>
 */

class OdysseqComponent extends BaseObject
{
    const RECEIVE_METHOD = 'payment.receive',
        CONTACT_METHOD = 'payment.contact',
        STATUS_METHOD = 'payment.status',
        CANCEL_METHOD = 'payment.cancel',
        SEND_METHOD = 'payment.send',
        BALANCE_METHOD = 'account.balance';

    const JSONRPC = '2.0';

    /**
     * @var string api access token of the partner in the Odysseq system
     */
    public $access_token;

    /**
     * @var string api notification secret key for verifying notifications about the final status of the payment request
     */
    public $notification_secret_key;

    /**
     * @var string api url
     */
    private $api_url = 'http://api.odysseq.com/partner/v1/json';

    /**
     * @return void
     * @throws InvalidConfigException
     */
    public function init()
    {
        if (!$this->access_token) {
            throw new InvalidConfigException('Param "access_token" must be set.');
        }
        if (!$this->notification_secret_key) {
            throw new InvalidConfigException('Param "notification_secret_key" must be set.');
        }
        parent::init();
    }

    /**
     * Signature validation.
     *
     * @param string $hash the hash that came in the header 'x-api-signature-sha256'
     * @param array $data data on bid
     * @return bool
     */
    public function validateSignature($hash, $data)
    {
        return $hash === $this->generateSignature($data);
    }

    /**
     * Initializing a request for a deposit.
     *
     * Success response:
     * ```php
     * [
     *      'jsonrpc' => '2.0',
     *      'result' => [
     *          'orderId' => 'order-1',
     *          'paymentInfo' => [
     *              'status' => 'WAITING',
     *              'forwardingPayUrl' => 'https://oplata.qiwi.com/form/?invoice_uid=5e72599e-dd89-4008-8d3e-a37df10491cc'
     *          ]
     *      ],
     *      'id' => 1
     * ]
     * ```
     *
     * @param string $order_id id of the request in the partner's system
     * @param int|float $amount amount receivable
     * @param string $card_tail 4 last digits of the card number from which payment is expected. If the parameter is not specified, then it is assumed that payment from the qiwi wallet is expected
     * @param string $client_ip client's ip address (required to request payment from a card)
     * @return mixed
     * @throws ServerErrorHttpException|InvalidConfigException|Exception
     */
    public function makeReceiveRequest($order_id, $amount, $card_tail = null, $client_ip = null)
    {
        return $this->sendRequest([
            'jsonrpc' => self::JSONRPC,
            'method' => self::RECEIVE_METHOD,
            'params' => [
                'orderId' => (string)$order_id,
                'amount' => $this->prepareAmount($amount),
                'cardTail' => $card_tail,
                'clientIp' => $client_ip
            ],
            'id' => 1
        ]);
    }

    /**
     * Initiating a request for a deposit through the contact system.
     *
     * Success response:
     * ```php
     * [
     *      'jsonrpc' => '2.0',
     *      'result' => [
     *          'orderId' => 'contact-1',
     *          'paymentInfo' => [
     *              'status' => 'WAITING',
     *              'forwardingPayUrl' => 'https://online.contact-sys.com/payment-form/df0024da-ee9a-4748-9452-ce06036e9134/'
     *          ]
     *      ],
     *      'id' => 1
     * ]
     * ```
     *
     * @param string $order_id id of the bid in the partner's system
     * @param int|float $amount amount receivable
     * @param string $first_name first name
     * @param string $middle_name middle name
     * @param string $last_name last name
     * @param string $card_number card number
     * @return mixed
     * @throws ServerErrorHttpException|InvalidConfigException|Exception
     */
    public function makeContactRequest($order_id, $amount, $first_name, $middle_name, $last_name, $card_number)
    {
        return $this->sendRequest([
            'jsonrpc' => self::JSONRPC,
            'method' => self::CONTACT_METHOD,
            'params' => [
                'orderId' => (string)$order_id,
                'amount' => $this->prepareAmount($amount),
                'client' => [
                    'firstName' => $first_name,
                    'middleName' => $middle_name,
                    'lastName' => $last_name,
                    'cardNumber' => $card_number
                ]
            ],
            'id' => 1
        ]);
    }

    /**
     * Obtaining information about the bid.
     *
     * Success response:
     * ```php
     * [
     *      'jsonrpc' => '2.0',
     *      'result' => [
     *          'orderId' => 'order-1',
     *          'paymentInfo' => [
     *              'status' => 'SUCCESS'
     *          ]
     *      ],
     *      'id' => 1
     * ]
     * ```
     *
     * @param string $order_id id of the bid in the partner's system
     * @return mixed
     * @throws Exception|InvalidConfigException|ServerErrorHttpException
     */
    public function getPaymentStatus($order_id)
    {
        return $this->sendRequest([
            'jsonrpc' => self::JSONRPC,
            'method' => self::STATUS_METHOD,
            'params' => [
                'orderId' => (string)$order_id
            ],
            'id' => 1
        ]);
    }

    /**
     * Cancellation of the payment request. Cancellation is available for bids in the initial WAITING status.
     *
     * Success response:
     * ```php
     * [
     *      'jsonrpc' => '2.0',
     *      'result' => [
     *          'orderId' => 'order-1',
     *          'paymentInfo' => [
     *              'status' => 'CANCELED'
     *          ]
     *      ],
     *      'id' => 1
     * ]
     * ```
     *
     * @param string $order_id id of the bid in the partner's system
     * @return mixed
     * @throws ServerErrorHttpException|InvalidConfigException|Exception
     */
    public function makeCancelRequest($order_id)
    {
        return $this->sendRequest([
            'jsonrpc' => self::JSONRPC,
            'method' => self::CANCEL_METHOD,
            'params' => [
                'orderId' => (string)$order_id
            ],
            'id' => 1
        ]);
    }

    /**
     * Initialization of sending funds to the client's card or wallet.
     *
     * Success response:
     * ```php
     * [
     *      'jsonrpc' => '2.0',
     *      'result' => [
     *          'orderId' => 'order-1',
     *          'paymentInfo' => [
     *              'status' => 'WAITING'
     *          ]
     *      ],
     *      'id' => 1
     * ]
     * ```
     *
     * @param string $order_id id of the bid in the partner's system
     * @param int|float $amount amount receivable
     * @param string $receiver recipient's card or wallet number
     * @return mixed
     * @throws Exception|InvalidConfigException|ServerErrorHttpException
     */
    public function makeSendRequest($order_id, $amount, $receiver)
    {
        return $this->sendRequest([
            'jsonrpc' => self::JSONRPC,
            'method' => self::SEND_METHOD,
            'params' => [
                'orderId' => (string)$order_id,
                'amount' => $this->prepareAmount($amount),
                'receiver' => $receiver
            ],
            'id' => 1
        ]);
    }

    /**
     * Getting partner balance
     *
     * Success response:
     * ```php
     * [
     *      'jsonrpc' => '2.0',
     *      'result' => [
     *          'balance' => [
     *              'toWallet' => 100.00,
     *              'toCard' => 90.00
     *          ]
     *      ],
     *      'id' => 1
     * ]
     * ```
     *
     * @return mixed
     * @throws ServerErrorHttpException|InvalidConfigException|Exception
     */
    public function getAccountBalance()
    {
        return $this->sendRequest([
            'jsonrpc' => self::JSONRPC,
            'method' => self::BALANCE_METHOD,
            'id' => 1
        ]);
    }

    /**
     * Sending request to the api
     *
     * @param array $params request parameters
     * @return mixed
     * @throws ServerErrorHttpException|InvalidConfigException|Exception
     */
    private function sendRequest($params)
    {
        $client = new Client();
        $response = $client->createRequest()
            ->setFormat(Client::FORMAT_JSON)
            ->setMethod('POST')
            ->setHeaders([
                'Authorization' => 'Bearer '.$this->access_token,
                'cache-control' => 'no-cache'
            ])
            ->setUrl($this->api_url)
            ->setData($params)
            ->send();
        if ($response->isOk) {
            if ($response->data) {
                return $response->data;
            } else {
                throw new ServerErrorHttpException('Empty data returned.');
            }
        }
        throw new ServerErrorHttpException('Odysseq responded with status code: '.$response->getStatusCode());
    }

    /**
     * Signature generation.
     *
     * @param array $data
     * @return bool|string
     */
    private function generateSignature($data)
    {
        $concat_val = $data['orderId'].'|'.$data['type'].'|'.$data['amount'].'|'.$data['status'];
        return hash_hmac('sha256', $concat_val, $this->notification_secret_key);
    }

    /**
     * @param int|float $amount amount receivable
     * @return string
     */
    private function prepareAmount($amount)
    {
        return number_format($amount, 2, '.', '');
    }
}