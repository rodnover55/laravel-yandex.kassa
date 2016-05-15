<?php
namespace Rnr\Tests\YandexKassa;


use Prophecy\Argument\Token\AnyValuesToken;
use Prophecy\Prophecy\ObjectProphecy;
use Rnr\Tests\YandexKassa\Mock\YandexKassaMock;
use Rnr\YandexKassa\Interfaces\LoggerInterface;
use Rnr\YandexKassa\Interfaces\OrderServiceInterface;
use Rnr\YandexKassa\Interfaces\YandexKassaInterface;
use Rnr\YandexKassa\YandexKassa;
use SimpleXMLElement;

class YandexKassaTest extends TestCase
{
    /** @var YandexKassaMock */
    private $paymentService;

    /**
     * @dataProvider actionsProvider
     */
    public function testGetMD5($action, $data) {
        $this->app->instance(OrderServiceInterface::class, $this->getOrderService()->reveal());
        $this->paymentService = $this->app->make(YandexKassaInterface::class);

        $md5 = $this->paymentService->getMD5($action, $data);

        $this->assertEquals(strtolower($data['md5']), strtolower($md5));
    }

    /**
     * @dataProvider actionsProvider
     */
    public function testMD5($action, $data) {
        $this->app->instance(OrderServiceInterface::class, $this->getOrderService()->reveal());
        $this->paymentService = $this->app->make(YandexKassaInterface::class);
        $this->assertTrue($this->paymentService->checkMD5($action, $data));
    }

    public function actionsProvider() {
        return [
            YandexKassa::CHECK => [YandexKassa::CHECK, $this->getFixture('request.json')],
            YandexKassa::AVISO => [YandexKassa::AVISO, $this->getFixture('aviso.json')]
        ];
    }

    public function testCheckSuccess() {
        $data =  $this->getFixture('request.json');

        $orderService = $this->getOrderService();
        $orderService->checkOrder($data['customerNumber'], $data['orderNumber'])->willReturn(null);
        $this->app->instance(OrderServiceInterface::class, $orderService->reveal());

        $this->post('/payment/yandex/check', $data);

        $this->assertResponseOk();

        $this->assertEquals('text/xml; charset=UTF-8',
            $this->response->headers->get('Content-Type'));

        $response = new SimpleXMLElement($this->response->getContent());

        $this->assertNotEmpty($response['performedDatetime']);
        $this->assertEquals(YandexKassa::CODE_SUCCESS, (int)$response['code']);
        $this->assertEquals($data['shopId'], (string)$response['shopId']);
        $this->assertEquals($data['invoiceId'], (string)$response['invoiceId']);
    }

    public function testAviso() {
        $data =  $this->getFixture('aviso.json');

        $orderService = $this->getOrderService();
        $orderService->checkOrder($data['customerNumber'], $data['orderNumber'])->willReturn(null);
        $this->app->instance(OrderServiceInterface::class, $orderService->reveal());

        $this->post('/payment/yandex/aviso', $data);

        $this->assertResponseOk();

        $this->assertEquals('text/xml; charset=UTF-8',
            $this->response->headers->get('Content-Type'));

        $response = new SimpleXMLElement($this->response->getContent());

        $this->assertEquals(YandexKassa::CODE_SUCCESS, (int)$response['code']);
    }

    public function testPaidCheck() {
        $data =  $this->getFixture('request.json');

        $orderService = $this->getOrderService();
        $orderService->checkOrder($data['customerNumber'], $data['orderNumber'])->willReturn('Order paid.');
        $this->app->instance(OrderServiceInterface::class, $orderService->reveal());

        $logger = $this->getLogger();
        $logger
            ->write(new AnyValuesToken())
            ->shouldBeCalled();
        $this->app->instance(LoggerInterface::class, $logger->reveal());

        $this->post('/payment/yandex/check', $data);

        $this->prophet->checkPredictions();
        $this->assertResponseOk();

        $this->assertEquals('text/xml; charset=UTF-8',
            $this->response->headers->get('Content-Type'));

        $response = new SimpleXMLElement($this->response->getContent());

        $this->assertEquals(YandexKassa::CODE_DECLINED, (int)$response['code']);
    }

    public function setUp()
    {
        parent::setUp();

        $this->app->alias(YandexKassaMock::class, YandexKassa::class);
    }

    /**
     * @return ObjectProphecy
     */
    protected function getOrderService() {
        return $this->prophet->prophesize()->willImplement(OrderServiceInterface::class);
    }
    
    protected function getLogger() {
        return $this->prophet->prophesize()->willImplement(LoggerInterface::class);
    }
}