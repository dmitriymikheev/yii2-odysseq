<?php

namespace dmitriymikheev\odysseq\actions;

use Yii;
use yii\base\Action;
use InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\helpers\Json;
use yii\web\BadRequestHttpException;
use dmitriymikheev\odysseq\forms\CallbackForm;

/**
 * Class CallbackAction
 *
 * @package dmitriymikheev\odysseq
 * @author Dmitriy Mikheev <dimamiheev92@gmail.com>
 */

class CallbackAction extends Action
{
    /**
     * @var callable
     */
    public $callback;

    /**
     * @var string component id in config file
     */
    public $component_id;

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    public function init()
    {
        if (!$this->callback) {
            throw new InvalidArgumentException('Param "callback" must be set.');
        }
        if (!$this->component_id) {
            throw new InvalidArgumentException('Param "component_id" must be set.');
        }
        parent::init();
    }

    /**
     * @return void
     * @throws BadRequestHttpException|InvalidConfigException
     */
    public function run()
    {
        $request = Yii::$app->getRequest();
        $signature = $request->getHeaders()->get('x-api-signature-sha256');
        if (!$signature) {
            throw new BadRequestHttpException();
        }

        $data = Json::decode($request->rawBody);
        $model = new CallbackForm;
        $model->load($data);

        if (!$model->validate() || !Yii::$app->get($this->component_id)->validateSignature($signature, $data)) {
            throw new BadRequestHttpException('Data is corrupted.');
        }

        call_user_func($this->callback, $model);
    }
}