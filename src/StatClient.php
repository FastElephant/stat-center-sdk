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
     * @return bool
     */
    public function dispatch(array $param, int $delay = 0): bool
    {
        $client = new Client(config('stat-center.endPoint'), config('stat-center.accessId'), config('stat-center.accessKey'));
        $queue = $client->getQueueRef(config('stat-center.queueName'));

        $data = json_encode($param, JSON_UNESCAPED_UNICODE);

        if (!$delay) {
            $request = new SendMessageRequest($data);
        } else {
            $request = new SendMessageRequest($data, $delay);
        }

        try {
            return $queue->sendMessage($request)->getMessageId();
        } catch (\Throwable $e) {
            return false;
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
