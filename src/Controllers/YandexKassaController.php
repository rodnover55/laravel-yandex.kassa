<?php
namespace Rnr\YandexKassa\Controllers;


use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Rnr\YandexKassa\Exceptions\YandexKassaException;
use Rnr\YandexKassa\Interfaces\YandexKassaInterface;
use Rnr\YandexKassa\Transformers\YandexTransformer;

class YandexKassaController extends Controller
{
    public function check(Request $request, YandexKassaInterface $payment,
                          YandexTransformer $transformer) {
        try {
            $data = $payment->check($request->all());
        } catch (YandexKassaException $e) {
            $data = $e->getData();
        }

        return response($transformer->transformCheck($data), Response::HTTP_OK, [
            'Content-Type' => 'text/xml'
        ]);
    }

    public function aviso(Request $request, YandexKassaInterface $payment,
                          YandexTransformer $transformer) {
        try {
            $data = $payment->aviso($request->all());
        } catch (YandexKassaException $e) {
            $data = $e->getData();
        }
        return response($transformer->transformAviso($data), Response::HTTP_OK, [
            'Content-Type' => 'text/xml'
        ]);
    }
}