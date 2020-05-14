# yokel/component

Наследник от CBitrixComponent

## Системные требования

- PHP 5.6 и выше

## Использование

В кастомных компонентах Битрикс в качестве родителя.

```php
use Yokel\Component\Component;

class ComponentName extends Component {

    // Выполняется по умолчанию
    public function actionStart() {
        // ...
    }
    
    // GET|POST|PUT|DELETE
    // .../?component=component.name&action=first
    public function actionFirst() {
        // Буфер вывода очищается
        // ...         
        // Работа скрипта завершается
    }
    
    // Результат в json (например, для ajax-запросов)
    // Шаблон компонента не подключается, возвращается json-строка
    public function actionJson() {
        // установим тип результата json
        $this->resultType = self::RESULT_TYPE_JSON;
        
        $this->arResult = [
            'foo' => 'bar',
            'some' => 'var'
        ];
    }

}
```
