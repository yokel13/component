<?php

/**
 * @author      Andrey Konovalov <hello@yokel.tech>
 * @copyright   Copyright (c), 2020 Andrey Konovalov
 * @license     MIT public license
 */
namespace Yokel\Component;

/**
 * Class Component
 *
 * @package Yokel
 */
class Component extends \CBitrixComponent {

    const RESULT_SUCCESS = 'success';
    const RESULT_ERROR = 'error';
    const RESULT_TYPE_HTML = 'html';
    const RESULT_TYPE_JSON = 'json';
    const DEFAULT_ACTION = 'Start';

    /**
     * @var string Пространство имён для компонентов наследников
     */
    private $namespace = 'yokel';

    /**
     * @var bool Флаг AJAX-запроса
     */
    private $isAjax = false;

    /**
     * @var array Параметры кеширования
     */
    private $cacheParams = [];

    /**
     * @var bool Флаг активности кеша
     */
    private $cacheEnabled = false;

    /**
     * @var int Время кеширование
     */
    private $cacheTime = 3600;

    /**
     * @var string Идентификатор кеша компонента
     */
    private $cacheID = null;

    /**
     * @var string Путь кеширования
     */
    private $cachePath = '/';

    /**
     * @var string Тип возвращаемого результата
     */
    protected $resultType = self::RESULT_TYPE_HTML;

    /**
     * @var array Параметры компонента
     */
    protected $params = [];

    /**
     * @var string Страница шаблона (по умолчанию template.php)
     */
    protected $templatePage = '';

    /**
     * @var string Идентификатор компонента (namespace:componentName)
     */
    protected $component;

    /**
     * @var string Метод для выполнения
     */
    protected $action = self::DEFAULT_ACTION;

    /**
     * Создаёт идентификатор кеша компонента
     */
    protected function createCacheID() {
        $this->cacheID = md5($this->component.$this->action.json_encode($this->cacheParams));
    }

    /**
     * Определяет вызов через AJAX
     * @return bool
     */
    protected function isCalledAjax() {
        return ($this->component === $this->GetName() && $this->request->isAjaxRequest());
    }

    /**
     * Задаёт метод для выполнения
     * @return string
     */
    protected function prepareAction() {
        $this->action = $this->request->get('action');

        if (empty($this->action)) {
            $this->action = self::DEFAULT_ACTION;
        }

        return $this->action;
    }

    /**
     * Проверяет права доступа для заданного метода (в работе)
     *
     * @return bool
     */
    protected function isActionAllowed() {
        return true;
    }

    /**
     * Выполняет указанный метод
     */
    protected function doAction() {
        return is_callable([$this, "action" . $this->action]) ? call_user_func([$this, "action" . $this->action]) : false;
    }

    /**
     * Подготавливает параметры компонента
     * @param $arParams
     * @return mixed
     */
    public function onPrepareComponentParams($arParams) {
        // var
        $this->component = sprintf('%s:%s', $this->namespace, strtolower($this->request->get('component')));
        $this->params = $arParams;

        // параметры кеширования
        if (isset($arParams['CACHE_ENABLED'])) {
            $this->cacheEnabled = $arParams['CACHE_ENABLED'];
        }
        if (isset($arParams['CACHE_TIME'])) {
            $this->cacheTime = $arParams['CACHE_TIME'];
        }
        if (isset($arParams['CACHE_DIR']) && !empty($arParams['CACHE_DIR'])) {
            $this->cachePath = sprintf('/%s/%s', $this->GetName(), $arParams['CACHE_DIR']);
        } else {
            $this->cachePath = sprintf('/%s', $this->GetName());
        }

        return $arParams;
    }

    /**
     * Получает результат
     */
    public function executeComponent() {
        // это AJAX-запрос
        $this->isAjax = $this->isCalledAjax();

        // метод для выполнения
        $this->prepareAction();

        // if action is allowed then proceed
        if ($this->isActionAllowed()) {
            if ($this->cacheEnabled) {
                // кеширование включено
                $this->createCacheID();
                $obCache = \Bitrix\Main\Data\Cache::createInstance();
                if ($obCache->initCache($this->cacheTime, $this->cacheID, $this->cachePath)) {
                    // получаем данные из кеша
                    $this->arResult = $obCache->getVars();

                    // страница шаблона из кеша (если не template.php)
                    if (isset($this->arResult['templatePage'])) {
                        $this->templatePage = $this->arResult['templatePage'];
                    }
                } elseif ($obCache->startDataCache()) {
                    // данные в кеше отсутствуют - получаем
                    $this->arResult = $this->doAction();

                    if ($this->arResult) {
                        // сохраняем страницу шаблона в кеш
                        $this->arResult['templatePage'] = $this->templatePage;

                        $obCache->endDataCache($this->arResult);
                    }
                }
            } else {
                // кеш не используется
                $this->doAction();
            }

            // показать результат
            $this->showResult();
        }
    }

    /**
     * Отображает результат
     */
    public function showResult() {
        // var
        global $APPLICATION;

        if ($this->resultType === self::RESULT_TYPE_HTML) {
            // HTML
            if ($this->isAjax) {
                $APPLICATION->RestartBuffer();
            }

            $this->includeComponentTemplate($this->templatePage);

            if ($this->isAjax) {
                $this->finalActions(true);
            }
        } else {
            // JSON
            $APPLICATION->RestartBuffer();

            echo json_encode($this->arResult);

            $this->finalActions(true);
        }
    }

    /**
     * Установливает параметры состояния компонента для кеширования
     * @param $arParams
     */
    public function setCacheParams($arParams) {
        $this->cacheParams = $arParams;
    }

    /**
     * Последние необходимые действия
     * @param bool $die
     */
    public function finalActions($die = false) {
        // Добавить кастомные заголовки в ответ сервера
        \Bitrix\Main\Context::getCurrent()->getResponse()->flush();

        // Bitrix CMain->FinalActions()
        $GLOBALS['APPLICATION']->FinalActions();

        // die
        if ($die) {
            die();
        }
    }

}