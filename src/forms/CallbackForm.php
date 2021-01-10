<?php

namespace dmitriymikheev\odysseq\forms;

use yii\base\Model;

/**
 * Class CallbackForm
 *
 * @package dmitriymikheev\odysseq\actions
 * @author Dmitriy Mikheev <dimamiheev92@gmail.com>
 */

class CallbackForm extends Model
{
    const STATUS_WAITING = 'WAITING',
        STATUS_SENDING = 'SENDING',
        STATUS_SUCCESS = 'SUCCESS',
        STATUS_CANCELED = 'CANCELED';

    const TYPE_IN = 'IN',
        TYPE_OUT = 'OUT';

    /**
     * @var string
     */
    public $orderId;

    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $amount;

    /**
     * @var string
     */
    public $status;

    /**
     * @var string
     */
    public $errorCode;

    /**
     * @var string
     */
    public $errorMessage;

    /**
     * @return string
     */
    public function formName()
    {
        return '';
    }

    /**
     * @return array
     */
    public function rules()
    {
        return [
            [['orderId', 'amount', 'errorCode', 'errorMessage'], 'string'],
            ['type', 'in', 'range' => $this->getTypeItems()],
            ['status', 'in', 'range' => $this->getStatusItems()],
        ];
    }

    /**
     * @return array
     */
    private function getStatusItems()
    {
        return [
            self::STATUS_WAITING,
            self::STATUS_SENDING,
            self::STATUS_SUCCESS,
            self::STATUS_CANCELED
        ];
    }

    /**
     * @return array
     */
    private function getTypeItems()
    {
        return [
            self::TYPE_IN,
            self::TYPE_OUT
        ];
    }
}