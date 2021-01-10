# Yii2 Odysseq
***
Extension for working with the Odysseq service

## Official API documentation
***
https://odysseq.com/api

## Installation
***
The preferred way to install this extension is through [composer](https://getcomposer.org/download/).

Either run

```bash
composer require --prefer-dist dmitriymikheev/yii2-odysseq "*"
```

or add

```
"dmitriymikheev/yii2-odysseq": "*"
```

to the require section of your `composer.json` file.

## Usage
***
Add the component to your application config file.

```php
return [
    //some config code
    'components' => [
        'odysseq' => [
            'class' => 'dmitriymikheev\odysseq\OdysseqComponent',
            'access_token' => 'your_access_token',
            'notification_secret_key' => 'your_notification_secret_key',
        ],
    ]
    //some config code
];
```

To configure notifications about a change in the status of the bid, add the following code in your controller:

*1. Add an action:*

```php
public function actions()
{
    return [
        'odysseq-callback' => [
            'class' => \dmitriymikheev\odysseq\actions\CallbackAction::class,
            'callback' => [$this, 'successCallback'],
            'component_id' => 'odysseq' //component id that was specified in the config file
        ]
    ];
}
```

*2. Add VerbFilter:*

```php
public function behaviors()
{
    return [
        'verbs' => [
            'class' => VerbFilter::className(),
            'actions' => [
                'odysseq-callback' => ['post'],
            ],
        ],
    ];
}
```

*3. Disable csrf validation for callback action like this:*

```php
public function beforeAction($action)
{
    if ($action->id == 'odysseq-callback') {
        $this->enableCsrfValidation = false;
    }
    return parent::beforeAction($action);
}
```

*4. Add a callback function to your controller. It will be called if the request is not fake.*

`$model` properties:
* orderId - id of the bid in the partner's system
* type - operation type (IN | OUT)
* amount - transaction amount
* status - operation status (WAITING | SENDING | SUCCESS | CANCELED)
* errorCode - error code
* errorMessage - error message

```php
public function successCallback($model)
{
    //here you can change the status of the bid in your database
    
    //Remember that to inform the service about the successful processing of the notification, please return a json response {"status": 200}
    //Example:
    $response = Yii::$app->getResponse();
    $response->format = yii\web\Response::FORMAT_JSON;
    $response->data = ['status' => 200];
    return $response->send();
}
```

## Available public methods

Method | Description
-------|------------
validateSignature($hash, $data) | Signature validation
makeReceiveRequest($order_id, $amount, $card_tail = null, $client_ip = null) | Initializing a request for a deposit. https://odysseq.com/api#payment-receive
makeContactRequest($order_id, $amount, $first_name, $middle_name, $last_name, $card_number) | Initiating a request for a deposit through the contact system. https://odysseq.com/api#payment-contact
getPaymentStatus($order_id) | Obtaining information about the bid. https://odysseq.com/api#payment-status
makeCancelRequest($order_id) | Cancellation of the payment request. Cancellation is available for bids in the initial WAITING status. https://odysseq.com/api#payment-cancel
makeSendRequest($order_id, $amount, $receiver) | Initialization of sending funds to the client's card or wallet. https://odysseq.com/api#payment-send
getAccountBalance() | Getting partner balance. https://odysseq.com/api#account-balance
