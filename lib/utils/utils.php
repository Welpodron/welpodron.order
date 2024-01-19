<?

namespace Welpodron\Order;

use Bitrix\Main\Loader;
use Bitrix\Sale\Delivery\Services\Manager as DeliveryManager;
use Bitrix\Sale\PaySystem\Manager as PaySystemManager;
use Bitrix\Sale\PersonType as PersonTypeManager;
use Bitrix\Main\FileTable;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Context;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Security\Random;
use Bitrix\Main\UserTable;
use Bitrix\Main\UserPhoneAuthTable;
use Bitrix\Sale\Property;


class Utils
{
    static private function addUser($email = '', $phone = '', $name = '', $siteId = SITE_ID)
    {
        $userId = false;

        $email = $email ? trim((string)$email) : '';

        $login = $email;

        if (empty($email)) {
            if (!empty($phone)) {
                $login = $phone;
            }
        }

        if (empty($login)) {
            $login = Random::getString(5) . mt_rand(0, 99999);
        }

        $pos = mb_strpos($login, '@');
        if ($pos !== false) {
            $login = mb_substr($login, 0, $pos);
        }

        if (mb_strlen($login) > 47) {
            $login = mb_substr($login, 0, 47);
        }

        $login = str_pad($login, 3, '_');

        $dbUserLogin = \CUser::GetByLogin($login);

        if ($userLoginResult = $dbUserLogin->Fetch()) {
            do {
                $loginTmp = $login . mt_rand(0, 99999);
                $dbUserLogin = \CUser::GetByLogin($loginTmp);
            } while ($userLoginResult = $dbUserLogin->Fetch());

            $login = $loginTmp;
        }

        $groupIds = [];
        $defaultGroups = Option::get('main', 'new_user_registration_def_group', '');

        if (!empty($defaultGroups)) {
            $groupIds = explode(',', $defaultGroups);
        }

        $password = \CUser::GeneratePasswordByPolicy($groupIds);

        $fields = [
            'LOGIN' => $login,
            'NAME' => $name,
            'PASSWORD' => $password,
            'CONFIRM_PASSWORD' => $password,
            'EMAIL' => $email,
            'GROUP_ID' => $groupIds,
            'ACTIVE' => 'Y',
            'LID' => $siteId,
            'PERSONAL_PHONE' => $phone,
            // 'PHONE_NUMBER' => $phone,
        ];

        $user = new \CUser;
        $addResult = $user->Add($fields);

        if (intval($addResult) <= 0) {
            throw new \Exception((($user->LAST_ERROR <> '') ? ': ' . $user->LAST_ERROR : ''));
        } else {
            global $USER;

            $userId = intval($addResult);
            $USER->Authorize($addResult);

            if ($USER->IsAuthorized()) {
                \CUser::SendUserInfo($USER->GetID(), $siteId, 'Вы были успешно зарегистрированы. Для установки пароля к вашему аккаунту перейдите по ссылке ниже.', true);
            } else {
                throw new \Exception("Ошибка авторизации");
            }
        }

        return $userId;
    }

    static public function getPersonTypes()
    {
        if (!Loader::includeModule('catalog')) {
            return [];
        }

        if (!Loader::includeModule('sale')) {
            return [];
        }

        return PersonTypeManager::getList()->fetchAll();
    }

    static public function getDeliveries()
    {
        if (!Loader::includeModule('catalog')) {
            return [];
        }

        if (!Loader::includeModule('sale')) {
            return [];
        }

        $arDeliveries = [];

        foreach (DeliveryManager::getActiveList() as $arDelivery) {
            if ($arDelivery['LOGOTIP']) {
                $id = $arDelivery['LOGOTIP'];

                $file = FileTable::getList(['select' => ['ID', 'WIDTH', 'HEIGHT', 'CONTENT_TYPE', 'FILE_SIZE', 'DESCRIPTION'], 'filter' => [
                    '=ID' => $id
                ], 'limit' => 1])->fetch();

                $arDelivery['LOGOTIP'] = $file;
                $arDelivery['LOGOTIP']['SRC'] = \CFile::GetPath($id);
            }
            $arDeliveries[] = [
                'ID' => $arDelivery['ID'],
                'NAME' => $arDelivery['NAME'],
                'PREVIEW_PICTURE' => $arDelivery['LOGOTIP'],
            ];
        }

        return $arDeliveries;
    }

    static public function getPaySystems()
    {
        if (!Loader::includeModule('catalog')) {
            return [];
        }

        if (!Loader::includeModule('sale')) {
            return [];
        }

        $arPaySystems = [];

        foreach (PaySystemManager::getList([
            'select' => ['ID', 'NAME', 'LOGOTIP'],
            'filter' => ['ACTIVE' => 'Y'],
        ])->fetchAll() as $arPaySystem) {
            if ($arPaySystem['LOGOTIP']) {
                $id = $arPaySystem['LOGOTIP'];

                $file = FileTable::getList(['select' => ['ID', 'WIDTH', 'HEIGHT', 'CONTENT_TYPE', 'FILE_SIZE', 'DESCRIPTION'], 'filter' => [
                    '=ID' => $id
                ], 'limit' => 1])->fetch();

                $arPaySystem['LOGOTIP'] = $file;
                $arPaySystem['LOGOTIP']['SRC'] = \CFile::GetPath($id);
            }
            $arPaySystems[] = [
                'ID' => $arPaySystem['ID'],
                'NAME' => $arPaySystem['NAME'],
                'PREVIEW_PICTURE' => $arPaySystem['LOGOTIP'],
            ];
        }

        return $arPaySystems;
    }

    static public function getUserId($email = '', $phone = '', $name = '')
    {
        global $USER;

        if ($USER->IsAuthorized()) {
            return $USER->GetID();
        }

        if (
            (Option::get('main', 'new_user_email_uniq_check', '') === 'Y' || Option::get('main', 'new_user_phone_auth', '') === 'Y') && ($email != '' || $phone != '')
        ) {
            if ($email != '') {
                $res = UserTable::getRow([
                    'filter' => [
                        '=ACTIVE' => 'Y',
                        '=EMAIL' => $email,
                        '!=EXTERNAL_AUTH_ID' => array_diff(UserTable::getExternalUserTypes(), ['shop'])
                    ],
                    'select' => ['ID'],
                ]);

                if (isset($res['ID'])) {
                    return intval($res['ID']);
                }
            }

            if ($phone != '') {
                $res = UserTable::getRow([
                    'filter' => [
                        'ACTIVE' => 'Y',
                        '!=EXTERNAL_AUTH_ID' => array_diff(UserTable::getExternalUserTypes(), ['shop']),
                        [
                            'LOGIC' => 'OR',
                            '=PHONE_AUTH.PHONE_NUMBER' => UserPhoneAuthTable::normalizePhoneNumber($phone) ?: '',
                            '=PERSONAL_PHONE' => $phone,
                            '=PERSONAL_MOBILE' => $phone,
                        ],
                    ],
                    'select' => ['ID'],
                ]);
                if (isset($res['ID'])) {
                    return intval($res['ID']);
                }
            }

            return self::addUser($email, $phone, $name);
        } elseif ($email != '' || Option::get('main', 'new_user_email_required', '') === 'N') {
            return self::addUser($email, $phone, $name);
        }

        return \CSaleUser::GetAnonymousUserID();
    }

    static public function getOrderFields($personType = 1)
    {
        if (intval($personType) <= 0) {
            throw new \Exception("Передан неверный тип плательщика");
        }

        if (!Loader::includeModule('sale')) {
            throw new \Exception("Не удалось подключить модуль sale");
        }

        $arProps = [];

        $dbProps = Property::getList([
            'select' => [
                'ID',
                'IS_REQUIRED' => 'REQUIRED',
                'NAME',
                'TYPE',
                'SETTINGS',
                'IS_EMAIL',
                'IS_PROFILE_NAME',
                'IS_PAYER',
                'CODE',
                'IS_ZIP',
                'IS_PHONE',
                'IS_ADDRESS',
                'IS_ADDRESS_FROM',
                'IS_ADDRESS_TO',
            ],
            //! Игнорируем адреса и все поля которые содержат default_value   
            'filter' => [
                'PERSON_TYPE_ID' => $personType,
                'IS_ADDRESS' => "N",
                'ACTIVE' => 'Y',
                // 'REQUIRED' => 'Y',
                '!CODE' => false,
                'DEFAULT_VALUE' => false
            ],
        ]);

        while ($arProp = $dbProps->fetch()) {
            $arProps[] = $arProp;
        }

        return $arProps;
    }

    static public function getProductsInfo($arProductsRawIds = [])
    {
        $arElements = [];

        if (!Loader::includeModule('iblock')) {
            return $arElements;
        }

        if (!Loader::includeModule('catalog')) {
            return $arElements;
        }

        if (!Loader::includeModule('sale')) {
            return $arElements;
        }

        if (!$arProductsRawIds || !isset($arProductsRawIds) || empty($arProductsRawIds) || !is_array($arProductsRawIds)) {
            return $arElements;
        }

        $arProductsIds = [];

        foreach ($arProductsRawIds as $productId) {
            if (is_numeric($productId)) {
                $arProductsIds[] = $productId;
            }
        }

        if (!$arProductsIds || empty($arProductsIds)) {
            return $arElements;
        }

        $arFilter = [
            'SITE_ID' => Context::getCurrent()->getSite(),
            'CHECK_PERMISSIONS' => 'N',
            'ACTIVE' => 'Y',
            'AVAILABLE' => 'Y',
            'ID' => $arProductsIds
        ];
        $arOrder = [];
        $arGroup = false;
        $arSelect = [
            'ID', 'NAME', 'IBLOCK_ID', 'DETAIL_PAGE_URL', 'AVAILABLE', 'PREVIEW_PICTURE', 'DETAIL_PICTURE', 'TYPE'
        ];


        $dbElements = \CIBlockElement::GetList($arOrder, $arFilter, $arGroup, false, $arSelect);

        while ($dbElement = $dbElements->GetNextElement(false, false)) {
            $arFields = $dbElement->GetFields();

            $arSku = \CCatalogSku::GetProductInfo($arFields["ID"]);

            if (is_array($arSku)) {
                $dbParentItem = \CIBlockElement::GetList([], [
                    'SITE_ID' => Context::getCurrent()->getSite(), 'CHECK_PERMISSIONS' => 'N', 'IBLOCK_ID' => $arSku['IBLOCK_ID'], 'ID' => $arSku['ID']
                ], false, false, ['PREVIEW_PICTURE', 'DETAIL_PICTURE'])->Fetch();

                if ($dbParentItem) {
                    $arFields['PREVIEW_PICTURE'] = $dbParentItem['PREVIEW_PICTURE'];
                    $arFields['DETAIL_PICTURE'] = $dbParentItem['DETAIL_PICTURE'];
                }
            }

            if ($arFields['PREVIEW_PICTURE']) {
                $id = $arFields['PREVIEW_PICTURE'];

                $file = FileTable::getList(['select' => ['ID', 'WIDTH', 'HEIGHT', 'CONTENT_TYPE', 'FILE_SIZE', 'DESCRIPTION'], 'filter' => [
                    '=ID' => $id
                ], 'limit' => 1])->fetch();
                $arFields['PREVIEW_PICTURE'] = $file;
                $arFields['PREVIEW_PICTURE']['SRC'] = \CFile::GetPath($id);
            }

            if ($arFields['DETAIL_PICTURE']) {
                $id = $arFields['DETAIL_PICTURE'];

                $file = FileTable::getList(['select' => ['ID', 'WIDTH', 'HEIGHT', 'CONTENT_TYPE', 'FILE_SIZE', 'DESCRIPTION'], 'filter' => [
                    '=ID' => $id
                ], 'limit' => 1])->fetch();
                $arFields['DETAIL_PICTURE'] = $file;
                $arFields['DETAIL_PICTURE']['SRC'] = \CFile::GetPath($id);
            }

            $arProps = [];

            if (is_array($arSku)) {
                $arOffersProps = [];

                $dbOffersProps = \CIBlockProperty::GetList([], ["ACTIVE" => "Y", "IBLOCK_ID" => $arFields["IBLOCK_ID"]]);

                while ($arFetchRes = $dbOffersProps->Fetch()) {
                    $arOffersProps[] = $arFetchRes["ID"];
                };

                if (!empty($arOffersProps)) {
                    $dbProps = \CIBlockElement::GetProperty($arFields["IBLOCK_ID"], $arFields["ID"], [], [
                        "ID" => $arOffersProps
                    ]);

                    while ($dbProp = $dbProps->Fetch()) {
                        if ($dbProp['PROPERTY_TYPE'] == "E" && $dbProp['USER_TYPE'] == "SKU") {
                            continue;
                        }

                        if ($dbProp['USER_TYPE'] == 'directory') {
                            if (Loader::includeModule('highloadblock')) {
                                $entityRaw = HighloadBlockTable::getList([
                                    'filter' => [
                                        '=TABLE_NAME' => $dbProp['USER_TYPE_SETTINGS']['TABLE_NAME']
                                    ]
                                ])->fetch();

                                $entity = HighloadBlockTable::compileEntity($entityRaw);
                                $entityClass = $entity->getDataClass();

                                if ($entityClass) {
                                    $dbComplexProps = $entityClass::getList(array(
                                        'order' => array('UF_NAME' => 'ASC'),
                                        'select' => array('UF_NAME', 'UF_XML_ID', 'UF_FILE', 'ID'),
                                        'filter' => array('!UF_NAME' => false, 'UF_XML_ID' => $dbProp['VALUE'])
                                    ));

                                    $arProp = [
                                        'NAME' => $dbProp['NAME'],
                                        'CODE' => $dbProp['CODE'],
                                        'PROPERTY_TYPE' => $dbProp['PROPERTY_TYPE'],
                                        'USER_TYPE' => $dbProp['USER_TYPE'],
                                        'VALUE' => $dbProp['VALUE'],
                                        'VALUE_ENUM' => [],
                                    ];

                                    while ($dbComplexProp = $dbComplexProps->fetch()) {
                                        $arPropValue = [
                                            'NAME' => $dbComplexProp['UF_NAME'],
                                            'XML_ID' => $dbComplexProp['UF_XML_ID'],
                                            'FILE' => [
                                                'VALUE' => $dbComplexProp['UF_FILE']
                                            ],
                                            'ID' => $dbComplexProp['ID'],
                                        ];

                                        if ($arPropValue['FILE']['VALUE']) {
                                            $id = $arPropValue['FILE']['VALUE'];

                                            $file = FileTable::getList(['select' => ['ID', 'WIDTH', 'HEIGHT', 'CONTENT_TYPE', 'FILE_SIZE', 'DESCRIPTION'], 'filter' => [
                                                '=ID' => $arPropValue['FILE']
                                            ], 'limit' => 1])->fetch();

                                            if ($file) {
                                                $arPropValue['FILE'] = $file;
                                                $arPropValue['FILE']['SRC'] = \CFile::GetPath($id);
                                            }
                                        }


                                        $arProp['VALUE_ENUM'][] = $arPropValue;
                                    }

                                    $arProps[] = $arProp;
                                }
                            }
                        } else {
                            if ($dbProp['PROPERTY_TYPE'] === 'L') {
                                $arProps[] = [
                                    'NAME' => $dbProp['NAME'],
                                    'CODE' => $dbProp['CODE'],
                                    'VALUE' => $dbProp['VALUE'],
                                    'VALUE_ENUM' => $dbProp['VALUE_ENUM'],
                                    'PROPERTY_TYPE' => $dbProp['PROPERTY_TYPE'],
                                    'USER_TYPE' => $dbProp['USER_TYPE'],
                                ];
                            } else {
                                $arProps[] = [
                                    'NAME' => $dbProp['NAME'],
                                    'CODE' => $dbProp['CODE'],
                                    'VALUE' => $dbProp['VALUE'],
                                    'VALUE_ENUM' => $dbProp['VALUE_ENUM'],
                                    'PROPERTY_TYPE' => $dbProp['PROPERTY_TYPE'],
                                    'USER_TYPE' => $dbProp['USER_TYPE'],
                                ];
                            }
                        }
                    }
                }
            }

            $arFields['PRICE'] = \CCatalogProduct::GetOptimalPrice($arFields["ID"])['RESULT_PRICE']['DISCOUNT_PRICE'];

            $arElement = ['FIELDS' => $arFields, 'PROPS' => $arProps];

            $arElements[] = $arElement;
        }

        return $arElements;
    }
}
