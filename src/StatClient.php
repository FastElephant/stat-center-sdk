<?php

namespace FastElephant\Stat;

use AliyunMNS\Client;
use AliyunMNS\Requests\SendMessageRequest;
use GuzzleHttp\Client as HttpClient;

class StatClient
{
    /**
     * 请求值
     * @var array
     */
    protected $request = [];

    /**
     * 返回值
     * @var array
     */
    protected $response = [];

    /**
     * @return array
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    /**
     * @return array
     */
    public function getResponse(): array
    {
        return $this->response;
    }

    /**
     * @param array $param
     * @param int $delay
     * @return array
     */
    public function dispatch(array $param, int $delay = 0): array
    {
        $client = new Client(config('stat-center.endPoint'), config('stat-center.accessId'), config('stat-center.accessKey'));
        $queue = $client->getQueueRef(config('stat-center.queueName'));

        $method = $param['method'] ?? 'create';
        $payload = $param['payload'] ?? [];

        if (!in_array($method, ['create', 'update', 'delete'])) {
            return ['code' => 500, 'msg' => 'method invalid!'];
        }
        if (!isset($param['archive_time'])) {
            return ['code' => 500, 'msg' => 'archive_time required!'];
        }
        if (empty($payload)) {
            return ['code' => 500, 'msg' => 'payload required!'];
        }
        if (!isset($payload['type'])) {
            return ['code' => 500, 'msg' => 'payload.type required!'];
        }
        if (!isset($payload['timestamp'])) {
            return ['code' => 500, 'msg' => 'payload.timestamp required!'];
        }

        $payload['biz'] = intval(config('stat-center.biz'));
        $payload['type'] = intval($payload['type']);
        $payload['timestamp'] = intval($payload['timestamp']);

        $param['archive_time'] = intval($param['archive_time']);
        $param['method'] = $method;
        $param['payload'] = $payload;

        $data = json_encode($param, JSON_UNESCAPED_UNICODE);

        if (!$delay) {
            $request = new SendMessageRequest($data);
        } else {
            $request = new SendMessageRequest($data, $delay);
        }

        try {
            $messageId = $queue->sendMessage($request)->getMessageId();
            return ['code' => 0, 'task_id' => $messageId];
        } catch (\Throwable $e) {
            return ['code' => 500, 'msg' => $e->getMessage()];
        }
    }

    /**
     * @param string $name
     * @param array $filter
     * @return array
     */
    public function command(string $name, array $filter): array
    {
        $param = [
            'biz' => config('stat-center.biz'),
            'name' => $name,
            'filter' => $filter,
        ];
        return $this->call('command', $param);
    }

    /**
     * @param $path
     * @param array $param
     * @return array
     */
    protected function call($path, array $param = []): array
    {
        $apiUrl = config('stat-center.url') . $path;

        $client = new HttpClient(['verify' => false, 'timeout' => config('stat-center.timeout')]);

        $this->request = $param;

        $startTime = $this->millisecond();

        try {
            $strResponse = $client->post($apiUrl, ['json' => $this->request])->getBody()->getContents();
        } catch (\Throwable $e) {
            $strResponse = $e->getMessage();
            return ['code' => 550, 'msg' => $strResponse];
        } finally {
            $expendTime = intval($this->millisecond() - $startTime);
            $this->monitorProcess($path, json_encode($this->request, JSON_UNESCAPED_UNICODE), $strResponse, $expendTime);
        }

        if (!$strResponse) {
            return ['code' => 555, 'msg' => '响应值为空', 'request_id' => ''];
        }

        $arrResponse = json_decode($strResponse, true);
        if (!$arrResponse) {
            return ['code' => 555, 'msg' => '响应值格式错误', 'request_id' => ''];
        }

        $this->response = $arrResponse;
        if ($arrResponse['code'] != 0) {
            return ['code' => $arrResponse['code'], 'msg' => $arrResponse['msg'], 'request_id' => $arrResponse['request_id']];
        }

        return ['code' => 0, 'result' => $arrResponse['result'], 'request_id' => $arrResponse['request_id']];
    }

    /**
     * 监控请求过程（交给子类实现）
     * @param $path
     * @param $strRequest
     * @param $strResponse
     * @param $expendTime
     */
    public function monitorProcess($path, $strRequest, $strResponse, $expendTime)
    {
    }

    /**
     * 获取当前时间毫秒时间戳
     * @return float
     */
    protected function millisecond(): float
    {
        list($mSec, $sec) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($mSec) + floatval($sec)) * 1000);
    }
}
