<?

use Bitrix\Main\Loader;

CJSCore::RegisterExt('welpodron.order', [
    'js' => '/local/packages/welpodron.order/iife/order/index.js',
    'skip_core' => true,
    'rel' => ['welpodron.core.templater'],
]);

//! ОБЯЗАТЕЛЬНО 

Loader::registerAutoLoadClasses(
    'welpodron.order',
    [
        'Welpodron\Order\Utils' => 'lib/utils/utils.php',
    ]
);
