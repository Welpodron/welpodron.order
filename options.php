<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use Welpodron\Order\Utils;

$moduleId = 'welpodron.order';

Loader::includeModule($moduleId);

$arPersonTypes['-1'] = 'Выберите тип плательщика';

foreach (Utils::getPersonTypes() as $arPersonType) {
    $arPersonTypes[$arPersonType['ID']] = '[' . $arPersonType['ID'] . '] ' . $arPersonType["NAME"];
}

$arDeliveries['-1'] = 'Выберите тип доставки';

foreach (Utils::getDeliveries() as $arDelivery) {
    $arDeliveries[$arDelivery['ID']] = '[' . $arDelivery['ID'] . '] ' . $arDelivery["NAME"];
}

$arPaySystems['-1'] = 'Выберите тип оплаты';

foreach (Utils::getPaySystems() as $arPaySystem) {
    $arPaySystems[$arPaySystem['ID']] = '[' . $arPaySystem['ID'] . '] ' . $arPaySystem["NAME"];
}

$arTabs = [
    [
        'DIV' => 'edit1',
        'TAB' => 'Настройки заказа',
        'TITLE' => 'Настройки заказа',
        'GROUPS' => [
            [
                'TITLE' => 'Основные настройки',
                'OPTIONS' => [
                    [
                        'NAME' => 'PERSON_TYPE_DEFAULT',
                        'LABEL' => 'Тип плательщика по умолчанию',
                        'VALUE' => Option::get($moduleId, 'PERSON_TYPE_DEFAULT'),
                        'TYPE' => 'selectbox',
                        'OPTIONS' => $arPersonTypes,
                    ],
                    [
                        'LABEL' => 'Если "Использовать при оформлении заказа доставку и оплату" не включено, то будет создаваться заказ без доставки и оплаты',
                        'TYPE' => 'note'
                    ],
                    [
                        'NAME' => 'USE_DELIVERY_AND_PAYMENT',
                        'LABEL' => 'Использовать при оформлении заказа доставку и оплату',
                        'VALUE' => Option::get($moduleId, 'USE_DELIVERY_AND_PAYMENT'),
                        'TYPE' => 'checkbox',
                    ],
                    [
                        'NAME' => 'DELIVERY_DEFAULT',
                        'LABEL' => 'Тип доставки по умолчанию',
                        'VALUE' => Option::get($moduleId, 'DELIVERY_DEFAULT'),
                        'TYPE' => 'selectbox',
                        'REL' => 'USE_DELIVERY_AND_PAYMENT',
                        'OPTIONS' => $arDeliveries,
                    ],
                    [
                        'NAME' => 'PAY_SYSTEM_DEFAULT',
                        'LABEL' => 'Тип оплаты по умолчанию',
                        'VALUE' => Option::get($moduleId, 'PAY_SYSTEM_DEFAULT'),
                        'TYPE' => 'selectbox',
                        'REL' => 'USE_DELIVERY_AND_PAYMENT',
                        'OPTIONS' => $arPaySystems,
                    ],
                ],
            ],
            [
                'TITLE' => 'Настройки внешнего вида ответа',
                'OPTIONS' => [
                    [
                        'NAME' => 'SUCCESS_FILE',
                        'LABEL' => 'PHP файл-шаблон успешного ответа',
                        'VALUE' => Option::get($moduleId, 'SUCCESS_FILE'),
                        'TYPE' => 'file',
                    ],
                    [
                        'NAME' => 'ERROR_FILE',
                        'LABEL' => 'PHP файл-шаблон ответа с ошибкой',
                        'VALUE' => Option::get($moduleId, 'ERROR_FILE'),
                        'TYPE' => 'file',
                    ],
                    [
                        'LABEL' => 'Если PHP файл-шаблон успешного ответа не задан, то будет использоваться содержимое успешного ответа по умолчанию',
                        'TYPE' => 'note'
                    ],
                    [
                        'NAME' => 'SUCCESS_CONTENT_DEFAULT',
                        'LABEL' => 'Содержимое успешного ответа по умолчанию',
                        'VALUE' => Option::get($moduleId, 'SUCCESS_CONTENT_DEFAULT'),
                        'TYPE' => 'editor',
                    ],
                    [
                        'LABEL' => 'Если PHP файл-шаблон ответа с ошибкой не задан, то будет использоваться содержимое ответа с ошибкой по умолчанию',
                        'TYPE' => 'note'
                    ],
                    [
                        'NAME' => 'ERROR_CONTENT_DEFAULT',
                        'LABEL' => 'Содержимое ответа с ошибкой по умолчанию',
                        'VALUE' => Option::get($moduleId, 'ERROR_CONTENT_DEFAULT'),
                        'TYPE' => 'editor',
                    ],
                ],
            ]
        ],
    ],
    [
        'DIV' => 'edit2',
        'TAB' => 'Настройки формы заказа',
        'TITLE' => 'Настройки формы заказа',
        'GROUPS' => [
            [
                'TITLE' => 'Валидация данных',
                'OPTIONS' => [
                    [
                        'NAME' => 'BANNED_SYMBOLS',
                        'LABEL' => 'Список запрещенных символов/слов (через запятую)',
                        'VALUE' => Option::get($moduleId, 'BANNED_SYMBOLS'),
                        'TYPE' => 'textarea',
                    ],
                ],
            ],
            [
                'TITLE' => 'Согласие на обработку персональных данных',
                'OPTIONS' => [
                    [
                        'NAME' => 'USE_AGREEMENT_CHECK',
                        'LABEL' => 'Проверять в данных, пришедших с клиента, наличие согласия на обработку персональных данных',
                        'VALUE' => Option::get($moduleId, 'USE_AGREEMENT_CHECK'),
                        'TYPE' => 'checkbox',
                    ],
                    [
                        'NAME' => 'AGREEMENT_PROPERTY',
                        'LABEL' => 'Код поля в котором хранится согласие на обработку персональных данных',
                        'VALUE' => Option::get($moduleId, 'AGREEMENT_PROPERTY'),
                        'TYPE' => 'text',
                        'REL' => 'USE_AGREEMENT_CHECK',
                    ],
                ],
            ],
            [
                'TITLE' => 'Настройки Google reCAPTCHA v3',
                'OPTIONS' => [
                    [
                        'NAME' => 'USE_CAPTCHA',
                        'LABEL' => 'Использовать Google reCAPTCHA v3',
                        'VALUE' => Option::get($moduleId, 'USE_CAPTCHA'),
                        'TYPE' => 'checkbox',
                    ],
                    [
                        'NAME' => 'GOOGLE_CAPTCHA_SECRET_KEY',
                        'LABEL' => 'Секретный ключ',
                        'VALUE' => Option::get($moduleId, 'GOOGLE_CAPTCHA_SECRET_KEY'),
                        'TYPE' => 'text',
                        'REL' => 'USE_CAPTCHA',
                    ],
                    [
                        'NAME' => 'GOOGLE_CAPTCHA_PUBLIC_KEY',
                        'LABEL' => 'Публичный ключ (ключ сайта)',
                        'VALUE' => Option::get($moduleId, 'GOOGLE_CAPTCHA_PUBLIC_KEY'),
                        'TYPE' => 'text',
                        'REL' => 'USE_CAPTCHA',
                    ],
                ],
            ],
        ],
    ]
];

$request = Context::getCurrent()->getRequest();

if ($request->isPost() && $request['save'] && check_bitrix_sessid()) {
    foreach ($arTabs as $arTab) {
        foreach ($arTab['GROUPS'] as $arGroup) {
            foreach ($arGroup['OPTIONS'] as $arOption) {
                if ($arOption['TYPE'] == 'note') continue;

                $value = $request->getPost($arOption['NAME']);

                if ($arOption['TYPE'] == "checkbox" && $value != "Y") {
                    $value = "N";
                } elseif (is_array($value)) {
                    $value = implode(",", $value);
                } elseif ($value === null) {
                    $value = '';
                }

                Option::set($moduleId, $arOption['NAME'], $value);
            }
        }
    }

    LocalRedirect($APPLICATION->GetCurPage() . '?lang=' . LANGUAGE_ID . '&mid_menu=1&mid=' . urlencode($moduleId) .
        '&tabControl_active_tab=' . urlencode($request['tabControl_active_tab']));
}

$tabControl = new CAdminTabControl("tabControl", $arTabs, true, true);
?>

<form name=<?= str_replace('.', '_', $moduleId) ?> method='post'>
    <? $tabControl->Begin(); ?>
    <?= bitrix_sessid_post(); ?>
    <? foreach ($arTabs as $arTab) : ?>
        <? $tabControl->BeginNextTab(); ?>
        <? foreach ($arTab['GROUPS'] as $arGroup) : ?>
            <tr class="heading">
                <td colspan="2"><?= $arGroup['TITLE'] ?></td>
            </tr>
            <? foreach ($arGroup['OPTIONS'] as $arOption) : ?>
                <tr>
                    <? if ($arOption['REL']) : ?>
                        <script>
                            (() => {
                                const init = () => {
                                    const relation = document.getElementById('<?= $arOption['REL'] ?>');

                                    if (!relation) {
                                        return;
                                    }

                                    const element = document.getElementById('<?= $arOption['NAME'] ?>');

                                    if (!element) {
                                        return;
                                    }

                                    const tr = element.closest('tr');

                                    const toggle = () => {
                                        if (relation.type === "checkbox" || relation.type === "radio") {
                                            if (relation.checked) {
                                                if (tr) {
                                                    tr.style.display = '';
                                                }

                                                element.removeAttribute('disabled');
                                            } else {
                                                if (tr) {
                                                    tr.style.display = 'none';
                                                }

                                                element.setAttribute('disabled', 'disabled');
                                            }

                                            return;
                                        }

                                        if (relation.value) {
                                            if (tr) {
                                                tr.style.display = '';
                                            }

                                            element.removeAttribute('disabled');
                                        } else {
                                            if (tr) {
                                                tr.style.display = 'none';
                                            }

                                            element.setAttribute('disabled', 'disabled');
                                        }
                                    }

                                    toggle();

                                    relation.addEventListener('input', toggle);
                                }

                                if (document.readyState === 'loading') {
                                    document.addEventListener('DOMContentLoaded', init, {
                                        once: true
                                    });
                                } else {
                                    init();
                                }
                            })();
                        </script>
                    <? endif ?>
                    <td style="width: 40%;">
                        <? if ($arOption['TYPE'] != 'note') : ?>
                            <label for="<?= $arOption['NAME'] ?>">
                                <?= $arOption['LABEL'] ?>
                            </label>
                        <? endif ?>
                    </td>
                    <td>
                        <? if ($arOption['TYPE'] == 'note') : ?>
                            <div class="adm-info-message">
                                <?= $arOption['LABEL'] ?>
                            </div>
                        <? elseif ($arOption['TYPE'] == 'checkbox') : ?>
                            <input <? if ($arOption['VALUE'] == "Y") echo "checked "; ?> type="checkbox" name="<?= htmlspecialcharsbx($arOption['NAME']) ?>" id="<?= htmlspecialcharsbx($arOption['NAME']) ?>" value="Y">
                        <? elseif ($arOption['TYPE'] == 'textarea') : ?>
                            <textarea id="<?= htmlspecialcharsbx($arOption['NAME']) ?>" rows="5" cols="80" name="<?= htmlspecialcharsbx($arOption['NAME']) ?>"><?= $arOption['VALUE'] ?></textarea>
                        <? elseif ($arOption['TYPE'] == 'selectbox') : ?>
                            <select id="<?= htmlspecialcharsbx($arOption['NAME']) ?>" name="<?= htmlspecialcharsbx($arOption['NAME']) ?>">
                                <? foreach ($arOption['OPTIONS'] as $key => $value) : ?>
                                    <option <? if ($arOption['VALUE'] == $key) echo "selected "; ?> value="<?= $key ?>"><?= $value ?></option>
                                <? endforeach; ?>
                            </select>
                        <? elseif ($arOption['TYPE'] == 'file') : ?>
                            <?
                            CAdminFileDialog::ShowScript(
                                array(
                                    "event" => str_replace('_', '', 'browsePath' . htmlspecialcharsbx($arOption['NAME'])),
                                    "arResultDest" => array("FORM_NAME" => str_replace('.', '_', $moduleId), "FORM_ELEMENT_NAME" => $arOption['NAME']),
                                    "arPath" => array("PATH" => GetDirPath($arOption['VALUE'])),
                                    "select" => 'F', // F - file only, D - folder only
                                    "operation" => 'O', // O - open, S - save
                                    "showUploadTab" => false,
                                    "showAddToMenuTab" => false,
                                    "fileFilter" => 'php',
                                    "allowAllFiles" => true,
                                    "SaveConfig" => true,
                                )
                            );
                            ?>
                            <input type="text" id="<?= htmlspecialcharsbx($arOption['NAME']) ?>" name="<?= htmlspecialcharsbx($arOption['NAME']) ?>" size="80" maxlength="255" value="<?= htmlspecialcharsbx($arOption['VALUE']); ?>">&nbsp;<input type="button" name="<?= ('browse' . htmlspecialcharsbx($arOption['NAME'])) ?>" value="..." onClick="<?= (str_replace('_', '', 'browsePath' . htmlspecialcharsbx($arOption['NAME']))) ?>()">
                        <? elseif ($arOption['TYPE'] == 'editor') : ?>
                            <textarea id="<?= htmlspecialcharsbx($arOption['NAME']) ?>" rows="5" cols="80" name="<?= htmlspecialcharsbx($arOption['NAME']) ?>"><?= $arOption['VALUE'] ?></textarea>
                        <? else : ?>
                            <input id="<?= htmlspecialcharsbx($arOption['NAME']) ?>" name="<?= htmlspecialcharsbx($arOption['NAME']) ?>" type="text" size="80" maxlength="255" value="<?= $arOption['VALUE'] ?>">
                        <? endif; ?>
                    </td>
                </tr>
            <? endforeach; ?>
        <? endforeach; ?>
    <? endforeach; ?>
    <? $tabControl->Buttons(['btnApply' => false, 'btnCancel' => false, 'btnSaveAndAdd' => false]); ?>
    <? $tabControl->End(); ?>
</form>