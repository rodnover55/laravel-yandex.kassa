<?php

use Rnr\YandexKassa\Controllers\YandexKassaController;

Route::group([
    'prefix' => 'payment'
], function() {
    Route::post('/yandex/check', ['as' => 'payment.yandex.check', 'uses' => YandexKassaController::class . '@check']);
    Route::post('/yandex/aviso', ['as' => 'payment.yandex.aviso', 'uses' => YandexKassaController::class . '@aviso']);
});
