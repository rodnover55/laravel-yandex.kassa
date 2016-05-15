<?php
namespace Rnr\Tests\YandexKassa;


use Rnr\Tests\YandexKassa\Mock\YandexKassaMock;
use Rnr\YandexKassa\interfaces\YandexKassaInterface;
use Rnr\YandexKassa\YandexKassa;

class YandexKassaTest extends TestCase
{
    /** @var YandexKassaMock */
    private $paymentService;

    /**
     * @dataProvider actionsProvider
     */
    public function testGetMD5($action, $data) {
        $md5 = $this->paymentService->getMD5($action, $data);

        $this->assertEquals(strtolower($data['md5']), strtolower($md5));
    }

    public function actionsProvider() {
        return [
            YandexKassa::CHECK => [YandexKassa::CHECK, $this->getFixture('request.json')],
            YandexKassa::AVISO => [YandexKassa::AVISO, $this->getFixture('aviso.json')]
        ];
    }

    /**
     * @dataProvider actionsProvider
     */
    public function testMD5($action, $data) {
        $this->assertTrue($this->paymentService->checkMD5($action, $data));
    }
    
    public function testCheck() {
        $data = $this->getRequestDataForNewOrder('request.json', [
            'status' => 'Новый',
            'is_paid' => false
        ], 'checkOrder');
        

        $data = $payment->check(array_merge([
            'ip' => $request->ip()
        ], $request->all()));
        
        
        Payment::where('order_id', $data['orderNumber'])->delete();

        $this->post('/payment/yandex/check', $data);

        $this->assertResponseOk();

        $this->assertEquals('text/xml; charset=UTF-8',
            $this->response->headers->get('Content-Type'));

        $response = new SimpleXMLElement($this->response->getContent());

        $this->assertNotEmpty($response['performedDatetime']);
        $this->assertEquals(YandexPayment::CODE_SUCCESS, (int)$response['code']);
        $this->assertEquals($data['shopId'], (string)$response['shopId']);
        $this->assertEquals($data['invoiceId'], (string)$response['invoiceId']);

        $this->seeInDatabase('payments', [
            'order_id' => $data['orderNumber'],
            'status' => PaymentFactory::CHECK
        ]);

        Payment::where('order_id', $data['orderNumber'])->delete();
    }

    public function testAviso() {
        $data = $this->getRequestDataForNewOrder('aviso.json', [
            'status' => 'Новый',
            'is_paid' => false
        ], 'paymentAviso');

        Payment::where('order_id', $data['orderNumber'])->delete();

        $this->withoutCSRF()->post('/payment/yandex/aviso', $data);

        $this->assertResponseOk();

        $this->assertEquals('text/xml; charset=UTF-8',
            $this->response->headers->get('Content-Type'));

        $response = new SimpleXMLElement($this->response->getContent());

        $this->assertEquals(YandexPayment::CODE_SUCCESS, (int)$response['code']);

        $this->seeInDatabase('payments', [
            'order_id' => $data['orderNumber'],
            'status' => PaymentFactory::SUCCESS
        ]);

        $this->seeInDatabase('orders', [
            'id' => $data['orderNumber'],
            'status' => OrderFactory::PAID
        ]);

        Payment::where('order_id', $data['orderNumber'])->delete();
        Order::where('id', $data['orderNumber'])->delete();
        Customer::where('id', $data['customerNumber'])->delete();
    }

    public function testPaidCheck() {
        $data = $this->getRequestDataForNewOrder('request.json', [
            'status' => 'Оплачен',
            'is_paid' => true
        ], 'checkOrder');

        $this->withoutCSRF()->post('/payment/yandex/check', $data);

        $this->assertResponseOk();

        $this->assertEquals('text/xml; charset=UTF-8',
            $this->response->headers->get('Content-Type'));

        $response = new SimpleXMLElement($this->response->getContent());

        $this->assertEquals(YandexPayment::CODE_DECLINED, (int)$response['code']);
    }

    protected function getRequestDataForNewOrder($fixtures, $orderOptions, $csrfAction) {
        $customer = factory(Customer::class)->create();
        $shipment = factory(Shipment::class)->create();
        $order = factory(Order::class)->create(array_merge(
            $orderOptions,
            [
                'customer_id' => $customer->id,
                'shipment_id' => $shipment->id
            ]
        ));

        $data = $this->getJsonFixture($fixtures, true);

        $data['orderNumber'] = $order->id;
        $data['customerNumber'] = $customer->id;
        $data['orderSumAmount'] = '500.00';

        $data['md5'] = $this->getMD5($csrfAction, $data);

        return $data;
    }

    protected function getMD5($action, $data) {
        $options = config('services.yandex_money');

        $str = implode(';', [
            $action, $data['orderSumAmount'], $data['orderSumCurrencyPaycash'],
            $data['orderSumBankPaycash'], $options['shopId'], $data['invoiceId'],
            $data['customerNumber'], $options['password']
        ]);

        return md5($str);
    }

    public function setUp()
    {
        parent::setUp();

        $this->app->alias(YandexKassaMock::class, YandexKassa::class);
        $this->paymentService = $this->app->make(YandexKassaInterface::class);
    }
}