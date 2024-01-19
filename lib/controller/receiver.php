<?

namespace Welpodron\Order\Controller;

use Bitrix\Blog\Util;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Error;
use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Fuser;
use Bitrix\Catalog\Product\Basket as _Basket;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Json;
use Bitrix\Main\UserConsent\Consent;
use Bitrix\Main\UserTable;
use Bitrix\Main\UserPhoneAuthTable;
use Bitrix\Sale\Order as _Order;
use Bitrix\Sale\DiscountCouponsManager;
use Bitrix\Sale\Delivery\Services\Manager as DeliveryManager;
use Bitrix\Sale\Delivery\Services\EmptyDeliveryService;
use Bitrix\Main\UserConsent\Agreement;

use Welpodron\Mutator\Utils as MutatorUtils;
use Welpodron\Order\Utils as OrderUtils;

// welpodron:order.Receiver.add

class Receiver extends Controller
{
    const DEFAULT_ORDER_MODULE_ID = 'welpodron.order';
    const DEFAULT_MUTATOR_MODULE_ID = 'welpodron.mutator';
    const DEFAULT_FIELD_VALIDATION_ERROR_CODE = "FIELD_VALIDATION_ERROR";
    const DEFAULT_FORM_GENERAL_ERROR_CODE = "FORM_GENERAL_ERROR";
    const DEFAULT_GOOGLE_URL = "https://www.google.com/recaptcha/api/siteverify";

    const DEFAULT_ERROR_CONTENT = "При обработке Вашего запроса произошла ошибка, повторите попытку позже или свяжитесь с администрацией сайта";

    public function configureActions()
    {
        return [
            'load' => [
                'prefilters' => [],
            ],
            'add' => [
                'prefilters' => []
            ]
        ];
    }

    private function loadModules()
    {
        if (!Loader::includeModule(self::DEFAULT_ORDER_MODULE_ID)) {
            throw new \Exception('Модуль ' . self::DEFAULT_ORDER_MODULE_ID . ' не удалось подключить');
        }

        if (!Loader::includeModule(self::DEFAULT_MUTATOR_MODULE_ID)) {
            throw new \Exception('Модуль ' . self::DEFAULT_MUTATOR_MODULE_ID . ' не удалось подключить');
        }

        if (!Loader::includeModule("catalog")) {
            throw new \Exception('Модуль catalog не удалось подключить');
        }

        if (!Loader::includeModule("sale")) {
            throw new \Exception('Модуль sale не удалось подключить');
        }
    }

    private function validateField($arField, $value, $bannedSymbols = [])
    {
        // Проверка на обязательность заполнения
        if ($arField['IS_REQUIRED'] == 'Y' && !strlen($value)) {
            $error = 'Поле: "' . $arField['NAME'] . '" является обязательным для заполнения';
            $this->addError(new Error($error, self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $arField['CODE']));
            return [
                'FIELD_CODE' => $arField['CODE'],
                'FIELD_VALUE' => $value,
                'FIELD_VALID' => false,
                'FIELD_ERROR' => $error,
            ];
        }

        // Проверка на наличие запрещенных символов 
        if (strlen($value)) {
            if ($bannedSymbols) {
                foreach ($bannedSymbols as $bannedSymbol) {
                    if (strpos($value, $bannedSymbol) !== false) {
                        $error = 'Поле: "' . $arField['NAME'] . '" содержит один из запрещенных символов: "' . implode(' ', $bannedSymbols) . '"';
                        $this->addError(new Error($error, self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $arField['CODE']));
                        return [
                            'FIELD_CODE' => $arField['CODE'],
                            'FIELD_VALUE' => $value,
                            'FIELD_VALID' => false,
                            'FIELD_ERROR' => $error,
                        ];
                    }
                }
            }
        }

        return [
            'FIELD_CODE' => $arField['CODE'],
            'FIELD_VALUE' => $value,
            'FIELD_VALID' => true,
            'FIELD_ERROR' => '',
        ];
    }

    private function validateCaptcha($token)
    {
        if (!$token) {
            throw new \Exception('Ожидался токен от капчи. Запрос должен иметь заполненное POST поле: "g-recaptcha-response"');
        }

        $secretCaptchaKey = Option::get(self::DEFAULT_ORDER_MODULE_ID, 'GOOGLE_CAPTCHA_SECRET_KEY');

        $httpClient = new HttpClient();
        $googleCaptchaResponse = Json::decode($httpClient->post(self::DEFAULT_GOOGLE_URL, ['secret' => $secretCaptchaKey, 'response' => $token], true));

        if (!$googleCaptchaResponse['success']) {
            throw new \Exception('Произошла ошибка при попытке обработать ответ от сервера капчи, проверьте задан ли параметр "GOOGLE_CAPTCHA_SECRET_KEY" в настройках модуля');
        }
    }

    private function validateAgreement($arDataRaw)
    {
        $agreementProp = Option::get(self::DEFAULT_ORDER_MODULE_ID, 'AGREEMENT_PROPERTY');

        $agreementId = intval($arDataRaw[$agreementProp]);

        if ($agreementId <= 0) {
            $error = 'Поле является обязательным для заполнения';
            $this->addError(new Error($error, self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $agreementProp));
            return;
        }

        $agreement = new Agreement($agreementId);

        if (!$agreement->isExist() || !$agreement->isActive()) {
            throw new \Exception('Соглашение c id ' . $agreementId . ' не найдено или не активно');
        }

        return true;
    }

    public function loadAction()
    {
        try {
            $this->loadModules();

            if (!_Basket::isNotCrawler()) {
                throw new \Exception('Поисковые роботы не могут оформлять заказ');
            }

            $request = $this->getRequest();
            $arDataRaw = $request->getPostList()->toArray();

            if ($arDataRaw['sessid'] !== bitrix_sessid()) {
                throw new \Exception('Неверный идентификатор сессии');
            }

            $from  = $arDataRaw['from'];

            $arParams = [];

            if ($arDataRaw['args']) {
                $arParams['ARGS'] = JSON::decode($arDataRaw['args']);
            }

            if ($arDataRaw['argsSensitive']) {
                $arParams['ARGS_SENSITIVE'] = MutatorUtils::getMutationData($arDataRaw['argsSensitive']);
            }

            if ($arParams['ARGS_SENSITIVE']['PATH']) {
                $path = $arParams['ARGS_SENSITIVE']['PATH'];
            } else {
                throw new \Exception('Не указан путь к мутатору');
            }

            return MutatorUtils::getMutationContent($path, $arParams, $from);
        } catch (\Throwable $th) {
            if (CurrentUser::get()->isAdmin()) {
                $this->addError(new Error($th->getMessage(), $th->getCode(), $th->getTrace()));
                return;
            }

            try {
                $errorFile = Option::get(self::DEFAULT_ORDER_MODULE_ID, 'ERROR_FILE');

                if (!$errorFile) {
                    $this->addError(new Error(Option::get(self::DEFAULT_ORDER_MODULE_ID, 'ERROR_CONTENT_DEFAULT')));
                    return;
                }

                $this->addError(new Error(MutatorUtils::getMutationContent($errorFile)));
                return;
            } catch (\Throwable $th) {
                if (CurrentUser::get()->isAdmin()) {
                    $this->addError(new Error($th->getMessage(), $th->getCode(), $th->getTrace()));
                    return;
                } else {
                    $this->addError(new Error(Option::get(self::DEFAULT_ORDER_MODULE_ID, 'ERROR_CONTENT_DEFAULT')));
                    return;
                }
            }
        }
    }

    public function addAction()
    {
        global $APPLICATION;

        try {
            $this->loadModules();

            if (!_Basket::isNotCrawler()) {
                throw new \Exception('Поисковые роботы не могут оформлять заказ');
            }

            if (!$_SERVER['HTTP_USER_AGENT']) {
                throw new \Exception('Поисковые роботы не могут оформлять заказ');
            } elseif (preg_match('/bot|crawl|curl|dataprovider|search|get|spider|find|java|majesticsEO|google|yahoo|teoma|contaxe|yandex|libwww-perl|facebookexternalhit/i', $_SERVER['HTTP_USER_AGENT'])) {
                throw new \Exception('Поисковые роботы не могут оформлять заказ');
            }

            $request = $this->getRequest();
            $arDataRaw = $request->getPostList()->toArray();

            if ($arDataRaw['sessid'] !== bitrix_sessid()) {
                throw new \Exception('Неверный идентификатор сессии');
            }

            // Проверка капчи если она включена
            $useCaptcha = Option::get(self::DEFAULT_ORDER_MODULE_ID, 'USE_CAPTCHA') == "Y";

            if ($useCaptcha) {
                $this->validateCaptcha($arDataRaw['g-recaptcha-response']);
            }

            $useCheckAgreement = Option::get(self::DEFAULT_ORDER_MODULE_ID, 'USE_AGREEMENT_CHECK') == "Y";

            if ($useCheckAgreement) {
                if (!$this->validateAgreement($arDataRaw)) {
                    return;
                }
            }

            $bannedSymbols = [];
            $bannedSymbolsRaw = preg_split('/(\s*,*\s*)*,+(\s*,*\s*)*/', trim(strval(Option::get(self::DEFAULT_ORDER_MODULE_ID, 'BANNED_SYMBOLS'))));
            if ($bannedSymbolsRaw) {
                $bannedSymbolsRawFiltered = array_filter($bannedSymbolsRaw, function ($value) {
                    return $value !== null && $value !== '';
                });
                $bannedSymbols = array_values($bannedSymbolsRawFiltered);
            }

            $arDataValid = [];

            $arFields = OrderUtils::getOrderFields(1);

            $arFields[] = [
                'NAME' => 'Комментарий',
                'CODE'  => 'USER_DESCRIPTION',
                'IS_REQUIRED' => 'N',
            ];

            $userName = '';
            $userEmail = '';
            $userPhone = '';

            foreach ($arFields as $arField) {
                $fieldCode = $arField['CODE'];
                $fieldValue = $arDataRaw[$fieldCode];

                $arResult = $this->validateField($arField, $fieldValue, $bannedSymbols);

                if ($arResult['FIELD_VALID']) {
                    $arDataValid[$fieldCode] = $fieldValue;

                    if ($arField['IS_PROFILE_NAME'] == "Y" && $arField['IS_PAYER'] == "Y") {
                        $userName = $fieldValue;
                    }

                    if ($arField['IS_EMAIL'] == "Y") {
                        $userEmail = $fieldValue;
                    }

                    if ($arField['IS_PHONE'] == "Y") {
                        $userPhone = $fieldValue;
                    }
                } else {
                    return;
                }
            }

            $siteId = Context::getCurrent()->getSite();

            // Битрикс сначала обрабатывает пользователя, а потом уже заказ
            $userId = OrderUtils::getUserId($userEmail, $userPhone, $userName);

            DiscountCouponsManager::init(DiscountCouponsManager::MODE_CLIENT, ['userId' => $userId]);

            $order = _Order::create($siteId, $userId);

            $order->isStartField();

            // Можно поменять тип плательщика из запроса
            $order->setPersonTypeId(1);

            $basket = Basket::create($siteId);

            $arProducts = [];

            if (is_array($arDataRaw['products'])) {
                foreach ($arDataRaw['products'] as $productId => $arProductInfo) {
                    if (intval($productId) <= 0) {
                        continue;
                    }

                    if (!is_array($arProductInfo) || !isset($arProductInfo['quantity'])) {
                        continue;
                    }

                    $productQuantity = intval($arProductInfo['quantity']);

                    if ($productQuantity > 0) {
                        $arProducts[] = [
                            'PRODUCT_ID' => $productId,
                            'PRODUCT_PROVIDER_CLASS' => '\Bitrix\Catalog\Product\CatalogProvider',
                            'QUANTITY' => $productQuantity
                        ];
                    }
                }
            }

            if (empty($arProducts)) {
                $error = 'Не выбраны товары для заказа';
                $this->addError(new Error($error, self::DEFAULT_FORM_GENERAL_ERROR_CODE));
                return;
            }

            foreach ($arProducts as $arProduct) {
                $item = $basket->createItem("catalog", $arProduct["PRODUCT_ID"]);
                unset($arProduct["PRODUCT_ID"]);
                $item->setFields($arProduct);
            }

            // Привязка корзины к заказу
            $order->setBasket($basket);

            $useDeliveryAndPayment = Option::get(self::DEFAULT_ORDER_MODULE_ID, 'USE_DELIVERY_AND_PAYMENT') == "Y";

            // if ($useDeliveryAndPayment) {
            //     $shipmentCollection = $order->getShipmentCollection();
            //     $shipment = $shipmentCollection->createItem();
            //     $shipmentItemCollection = $shipment->getShipmentItemCollection();
            //     $shipment->setField('CURRENCY', $order->getCurrency());

            //     foreach ($order->getBasket() as $item) {
            //         $shipmentItem = $shipmentItemCollection->createItem($item);
            //         $shipmentItem->setQuantity($item->getQuantity());
            //     }

            //     //! ВНИМАНИЕ ТЕКУЩАЯ ПОЛИТИКА ОФОРМЛЕНИЯ ЗАКАЗА ИДЕТ СНАЧАЛА СЧИТАЕТСЯ ДОСТАВКА ПОТОМ ОПЛАТА
            //     //! МОЖНО СДЕЛАТЬ НАОБОРОТ В НАСТРОЙКАХ МОДУЛЯ ДОБАВИТЬ ДАННУЮ ОПЦИЮ
            //     //! см if ($this->arParams['DELIVERY_TO_PAYSYSTEM'] === 'd2p') 

            //     //! ТУТ НУЖНО СМОТРЕТЬ ПРИШЛО ЛИ С ФРОНТА ПОЛЕ ДОСТАВКИ
            //     $deliveryServices = DeliveryManager::getRestrictedObjectsList($shipment);

            //     //? см protected function initDelivery
            //     if (!empty($deliveryServices)) {
            //         $order->isStartField();

            //         // $shipment->setFields([
            //         //     'DELIVERY_ID' => $deliveryId,
            //         //     'DELIVERY_NAME' => $name,
            //         //     'CURRENCY' => $order->getCurrency(),
            //         // ]);

            //         $shipmentCollection->calculateDelivery();

            //         $order->doFinalAction(true);
            //     } else {
            //         $service = DeliveryManager::getById(
            //             EmptyDeliveryService::getEmptyDeliveryServiceId()
            //         );
            //         $shipment->setFields([
            //             'DELIVERY_ID' => $service['ID'],
            //             'DELIVERY_NAME' => $service['NAME'],
            //             'CURRENCY' => $order->getCurrency(),
            //         ]);
            //     }

            //     //! ОПЛАТЫ см initPayment
            //     //! ТУТ подразумевается что пока что оплата внутренними системами не работает
            //     //! См getInnerPaySystemInfo

            //     $paymentCollection = $order->getPaymentCollection();

            //     $remainingSum = $order->getPrice() - $paymentCollection->getSum();

            //     if ($remainingSum > 0 || $order->getPrice() == 0) {
            //         $paymentItem = $paymentCollection->createItem();
            //         // $paymentItem->setFields([
            //         //     'PAY_SYSTEM_ID' => $selectedPaySystem['ID'],
            //         //     'PAY_SYSTEM_NAME' => $selectedPaySystem['NAME'],
            //         //     'SUM' => $remainingSum,
            //         // ]);
            //     } else {
            //         throw new \Exception('Невозможно создать заказ с текущей стоимостью');
            //     }

            //! Далее идет initEntityCompanyIds

            //! Далее идет initOrderFields 

            if ($arDataValid['USER_DESCRIPTION']) {
                $order->setField(
                    'USER_DESCRIPTION',
                    $arDataValid['USER_DESCRIPTION']
                );
            }

            unset($arDataValid['USER_DESCRIPTION']);

            //! Далее идет checkProperties 

            //! Далее идет setOrderProperties 

            $propertyCollection = $order->getPropertyCollection();

            foreach ($propertyCollection as $propertyObj) {
                $propertyCode = $propertyObj->getField('CODE');

                if (isset($arDataValid[$propertyCode])) {
                    $propertyObj->setValue($arDataValid[$propertyCode]);
                }
            }

            // ! Далее идет recalculatePayment 

            $order->doFinalAction(true);
            $result = $order->save();

            if (!$result->isSuccess()) {
                throw new \Exception(implode(', ', $result->getErrorMessages()));
            }

            if ($useCheckAgreement) {
                $agreementId = null;

                $agreementProp = Option::get(self::DEFAULT_ORDER_MODULE_ID, 'AGREEMENT_PROPERTY');

                if (isset($arDataValid[$agreementProp])) {
                    $agreementId = intval($arDataValid[$agreementProp]);
                } else {
                    $agreementId = intval($arDataRaw[$agreementProp]);
                }

                if ($agreementId > 0) {
                    Consent::addByContext($agreementId, null, null, [
                        'URL' => Context::getCurrent()->getServer()->get('HTTP_REFERER'),
                        'USER_ID' => $userId,
                    ]);
                }
            }

            $useSuccessContent = Option::get(self::DEFAULT_ORDER_MODULE_ID, 'USE_SUCCESS_CONTENT');

            $templateIncludeResult = "";

            if ($useSuccessContent == 'Y') {
                $templateIncludeResult =  Option::get(self::DEFAULT_ORDER_MODULE_ID, 'SUCCESS_CONTENT_DEFAULT');

                $successFile = Option::get(self::DEFAULT_ORDER_MODULE_ID, 'SUCCESS_FILE');

                if ($successFile) {
                    ob_start();
                    $APPLICATION->IncludeFile($successFile, [
                        'arMutation' => [
                            'PATH' => $successFile,
                            'PARAMS' => [],
                        ]
                    ], ["SHOW_BORDER" => false, "MODE" => "php"]);
                    $templateIncludeResult = ob_get_contents();
                    ob_end_clean();
                }
            } else {
                \LocalRedirect("/personal/orders/" . $result->getId());
                return;
            }

            return $templateIncludeResult;
        } catch (\Throwable $th) {
            if (CurrentUser::get()->isAdmin()) {
                $this->addError(new Error($th->getMessage(), $th->getCode(), $th->getTrace()));
                return;
            }

            try {
                $useErrorContent = Option::get(self::DEFAULT_ORDER_MODULE_ID, 'USE_ERROR_CONTENT');

                if ($useErrorContent == 'Y') {
                    $errorFile = Option::get(self::DEFAULT_ORDER_MODULE_ID, 'ERROR_FILE');

                    if (!$errorFile) {
                        $this->addError(new Error(Option::get(self::DEFAULT_ORDER_MODULE_ID, 'ERROR_CONTENT_DEFAULT'), self::DEFAULT_FORM_GENERAL_ERROR_CODE));
                        return;
                    }

                    ob_start();
                    $APPLICATION->IncludeFile($errorFile, [
                        'arMutation' => [
                            'PATH' => $errorFile,
                            'PARAMS' => [],
                        ]
                    ], ["SHOW_BORDER" => false, "MODE" => "php"]);
                    $templateIncludeResult = ob_get_contents();
                    ob_end_clean();
                    $this->addError(new Error($templateIncludeResult));
                    return;
                }

                $this->addError(new Error(self::DEFAULT_ERROR_CONTENT, self::DEFAULT_FORM_GENERAL_ERROR_CODE));
                return;
            } catch (\Throwable $th) {
                if (CurrentUser::get()->isAdmin()) {
                    $this->addError(new Error($th->getMessage(), $th->getCode(), $th->getTrace()));
                    return;
                } else {
                    $this->addError(new Error(self::DEFAULT_ERROR_CONTENT, self::DEFAULT_FORM_GENERAL_ERROR_CODE));
                    return;
                }
            }
        }
    }
}
