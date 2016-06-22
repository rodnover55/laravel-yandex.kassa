<?php
namespace Rnr\YandexKassa;


use Carbon\Carbon;
use Rnr\YandexKassa\Exceptions\ValidateException;
use Rnr\YandexKassa\Exceptions\YandexKassaException;
use Rnr\YandexKassa\Interfaces\LoggerInterface;
use Rnr\YandexKassa\Interfaces\OrderServiceInterface;
use Rnr\YandexKassa\Interfaces\YandexKassaInterface;

class YandexKassa implements YandexKassaInterface
{
    const CODE_SUCCESS = 0;
    const CODE_AUTH_ERROR = 1;
    const CODE_DECLINED = 100;
    const CODE_BAD_DATA = 200;

    const CHECK = 'checkOrder';
    const AVISO = 'paymentAviso';

    private $options;
    /** @var OrderServiceInterface */
    private $orderService;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(OrderServiceInterface $orderService, array $options,
                                LoggerInterface $logger = null) {
        $this->validate($options);
        
        $this->options = $options;
        $this->orderService = $orderService;
        $this->logger = $logger;
    }

    protected function validate(array $options) {
        $required = ['shopId', 'password'];
        
        $missedKeys = array_diff($required, array_keys($options));
        
        if (!empty($missedKeys)) {
            $keys = implode(', ', $missedKeys);

            throw new ValidateException("Missed required fields: {$keys}");
        }
    }

    /**
     * @param array $data
     * @return array
     * @throws YandexKassaException
     */
    public function check(array $data) {
        return $this->checkPreconditions(self::CHECK, $data);
    }

    /**
     * @param array $data
     * @return array
     * @throws YandexKassaException
     */
    public function aviso(array $data) {
        $response = $this->checkPreconditions(self::AVISO, $data);
        
        $this->orderService->changeOrder($data['orderNumber'], $data);
        
        return $response;
    }

    protected function checkMD5($action, $data) {
        return strtoupper($this->getMD5($action, $data)) === strtoupper($data['md5']);
    }

    protected function getMD5($action, $data) {
        $str = implode(';', [
            $action, $data['orderSumAmount'], $data['orderSumCurrencyPaycash'],
            $data['orderSumBankPaycash'], $this->options['shopId'], $data['invoiceId'],
            $data['customerNumber'], $this->options['password']
        ]);

        return md5($str);
    }

    protected function checkPreconditions($action, $data) {
        try {
            if (!$this->checkMD5($action, $data)) {
                throw new YandexKassaException(
                    $this->createData(self::CODE_AUTH_ERROR, $data, 'Неверный md5')
                );
            }

            $errorMessage = $this->orderService->checkOrder($data['customerNumber'], $data['orderNumber']);

            if (!empty($errorMessage)) {
                throw new YandexKassaException(
                    $this->createData(self::CODE_DECLINED, $data, $errorMessage)
                );
            }

            return $this->createData(self::CODE_SUCCESS, $data);
        } catch (YandexKassaException  $e) {
            if (!empty($this->logger)) {
                $this->logger->write($e->getData());
            }

            throw $e;
        }
    }

    protected function createData($code, $data, $message = null, $techMessage = null) {
        if (empty($techMessage)) {
            $techMessage = $message;
        }

        return [
            'performedDatetime' => Carbon::now()->toAtomString(),
            'code' => $code,
            'shopId' => $this->options['shopId'],
            'invoiceId' => $data['invoiceId'],
            'message' => $message,
            'techMessage' => $techMessage
        ];
    }

    protected function isSuccess($data) {
        return $data['code'] == self::CODE_SUCCESS;
    }
}