<?

use Bitrix\Main\Loader;

//! ОБЯЗАТЕЛЬНО 

if (!Loader::includeModule('welpodron.core')) {
    throw new \Exception('Модуль welpodron.core не удалось подключить');
}

CJSCore::RegisterExt('welpodron.order', [
    'js' => '/bitrix/js/welpodron.order/order/script.js',
    'skip_core' => true,
    'rel' => ['welpodron.core.templater'],
]);

CJSCore::RegisterExt('welpodron.order.form', [
    'js' => '/bitrix/js/welpodron.order/form/script.js',
    'skip_core' => true,
    'rel' => ['welpodron.core.templater'],
]);

Loader::registerAutoLoadClasses(
    'welpodron.order',
    [
        'Welpodron\Order\Utils' => 'lib/utils/utils.php',
    ]
);
