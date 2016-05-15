<?php
namespace Rnr\YandexKassa;


use Carbon\Carbon;
use Rnr\YandexKassa\Exceptions\ValidateException;
use Rnr\YandexKassa\Exceptions\YandexKassaException;
use Rnr\YandexKassa\interfaces\OrderServiceInterface;
use Rnr\YandexKassa\interfaces\YandexKassaInterface;

class YandexKassa implements YandexKassaInterface
{
    const CODE_SUCCESS = 0;
    const CODE_AUTH_ERROR = 1;
    const CODE_DECLINED = 100;
    const CODE_BAD_DATA = 200;

    const CHECK = 'checkOrder';
    const AVISO = 'paymentAviso';

    private $options;
    private $customerRepository;
    /** @var OrderServiceInterface */
    private $orderService;

    public function __construct(OrderServiceInterface $orderService, array $options) {
        $this->validate($options);
        $this->options = $options;
        $this->orderService = $orderService;
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
    public function check($data) {
        return $this->checkPreconditions(self::CHECK, $data);
    }

    public function aviso($data) {
        return $this->checkPreconditions(self::AVISO, $data);
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
        if (!$this->checkMD5($action, $data)) {
            throw new YandexKassaException(
                $this->createData($action, self::CODE_AUTH_ERROR, $data,
                    'Неверный md5')
            );
        }

        $customerId = $data['customerNumber'];
        $orderId = $data['orderNumber'];

        if (!$this->customerRepository->exists($data['customerNumber'])) {
            throw new YandexKassaException(
                $this->createData($action, self::CODE_DECLINED, $data,
                    'Пользователь не существует')
            );
        }

        if (!$this->customerRepository->hasOrder($customerId, $orderId)) {
            throw new YandexKassaException(
                $this->createData($action, self::CODE_DECLINED, $data,
                    'Заказ для данного пользователя не существует')
            );
        }

        $order = $this->orderService->item($orderId);

        if ($order['is_paid'] || !in_array($order['status'], ['Новый', 'В работе'])) {
            throw new YandexKassaException(
                $this->createData($action, self::CODE_DECLINED, $data,
                    'Заказ уже оплачен')
            );
        }

        if ((float)$order['amount'] !== (float)$data['orderSumAmount']) {
            throw new YandexKassaException(
                $this->createData($action, self::CODE_DECLINED, $data,
                    'Неверная сумма оплаты')
            );
        }

        return $this->createData($action, self::CODE_SUCCESS, $data);
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
}