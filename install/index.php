<?

use Bitrix\Main\ModuleManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\Config\Option;

class welpodron_order extends CModule
{
    private $DEFAULT_OPTIONS = [];

    public function __construct()
    {
        $this->MODULE_ID = 'welpodron.order';
        $this->MODULE_VERSION = '1.0.0';
        $this->MODULE_NAME = 'Модуль для работы с заказом (welpodron.order)';
        $this->MODULE_DESCRIPTION = 'Модуль для работы с заказом';
        $this->PARTNER_NAME = 'Welpodron';
        $this->PARTNER_URI = 'https://github.com/Welpodron';

        $this->DEFAULT_OPTIONS = [
            'PERSON_TYPE_DEFAULT' => '1',
            'USE_DELIVERY_AND_PAYMENT' => 'Y',
            'DELIVERY_DEFAULT' => '1',
            'PAY_SYSTEM_DEFAULT' => '1',
            'SUCCESS_CONTENT_DEFAULT' => '<p>Заказ успешно оформлен</p>',
            'ERROR_CONTENT_DEFAULT' => '<p>При обработке Вашего запроса произошла ошибка, повторите попытку позже или свяжитесь с администрацией сайта</p>',
            'BANNED_SYMBOLS' => '<,>,&,*,^,%,$,`,~,#,href,eval,script,/,\\,=,!,?',
            'USE_AGREEMENT_CHECK' => 'N',
            'AGREEMENT_PROPERTY' => 'agreement',
            'USE_CAPTCHA' => 'N'
        ];
    }

    public function InstallOptions()
    {
        global $APPLICATION;

        try {
            foreach ($this->DEFAULT_OPTIONS as $optionName => $optionValue) {
                Option::set($this->MODULE_ID, $optionName, $optionValue);
            }
        } catch (\Throwable $th) {
            $APPLICATION->ThrowException($th->getMessage() . '\n' . $th->getTraceAsString());
            return false;
        }
        return true;
    }

    public function UnInstallOptions()
    {
        global $APPLICATION;

        try {
            Option::delete($this->MODULE_ID);
        } catch (\Throwable $th) {
            $APPLICATION->ThrowException($th->getMessage() . '\n' . $th->getTraceAsString());
            return false;
        }
        return true;
    }

    public function InstallFiles()
    {
        global $APPLICATION;

        try {
            if (!CopyDirFiles(__DIR__ . '/js/', Application::getDocumentRoot() . '/bitrix/js', true, true)) {
                $APPLICATION->ThrowException('Не удалось скопировать js');
                return false;
            };
        } catch (\Throwable $th) {
            $APPLICATION->ThrowException($th->getMessage() . '\n' . $th->getTraceAsString());
            return false;
        }

        return true;
    }

    public function UnInstallFiles()
    {
        Directory::deleteDirectory(Application::getDocumentRoot() . '/bitrix/js/' . $this->MODULE_ID);
    }

    public function DoInstall()
    {
        global $APPLICATION;

        if (!CheckVersion(ModuleManager::getVersion('main'), '14.00.00')) {
            $APPLICATION->ThrowException('Версия главного модуля ниже 14.00.00');
            return false;
        }

        if (!Loader::includeModule('welpodron.core')) {
            $APPLICATION->ThrowException('Модуль welpodron.core не был найден');
            return false;
        }

        if (!Loader::includeModule("welpodron.mutator")) {
            $APPLICATION->ThrowException('Модуль welpodron.mutator не был найден');
            return false;
        }

        if (!Loader::includeModule("catalog")) {
            $APPLICATION->ThrowException('Модуль catalog не был найден');
            return false;
        }

        if (!Loader::includeModule("sale")) {
            $APPLICATION->ThrowException('Модуль sale не был найден');
            return false;
        }

        if (!$this->InstallFiles()) {
            return false;
        }

        if (!$this->InstallOptions()) {
            return false;
        }

        ModuleManager::registerModule($this->MODULE_ID);

        $APPLICATION->IncludeAdminFile('Установка модуля ' . $this->MODULE_ID, __DIR__ . '/step.php');
    }

    public function DoUninstall()
    {
        global $APPLICATION;

        $this->UnInstallFiles();
        $this->UnInstallOptions();

        ModuleManager::unRegisterModule($this->MODULE_ID);

        $APPLICATION->IncludeAdminFile('Деинсталляция модуля ' . $this->MODULE_ID, __DIR__ . '/unstep.php');
    }
}
