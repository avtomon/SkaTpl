<?php
/**
 * Created by PhpStorm.
 * User: Александр
 * Date: 18.11.14
 * Time: 16:33
 */

class SkaTplException extends Exception { }

class SkaTpl
{
    private $template = false; // Обрабатываемый шаблон

    const LABELS = ['id', 'title', 'class', 'value', 'src', 'data-val', 'type', 'for', 'name', 'prop', 'href', 'content', 'data-ulogin']; // Массив атрибутов DOM-эелемента возможных для заполнения

    private $clone = false; // Нужно ли клонировать объект DOM для вставки некольких записей

    function __construct (string $path = null)
    {
        if ($path)
        {
            if (!$this->template = file_get_contents("{$_SERVER['DOCUMENT_ROOT']}/$path"))
            {
                throw new SkaTplException("Не удалось получить шаблон $path");
            }
        }
    }

    /**
     * * Установить шаблон для изменения
     * 
     * @param string $code - разметра шаблона
     * @return string
     */
    public function setTemplate (string $code)
    {
        return $this->template = $code;
    }

    /**
     * Вернуть разметку обрабатываемого шаблона
     *
     * @return bool|string
     */
    public function getTemplate ()
    {
        return $this->template;
    }

    /**
     * Возвращает DOM-элементы для вставки
     *
     * @param $selector - селектор для поиска
     * @param array $parents - родительские элементы для поиска
     *
     * @return array
     */
    private function getLayers (string & $selector, array & $parents)
    {
        $this->clone = false;
        $new_parents = [];
        $classes = explode('.', $selector);
        $id = '';
        if (strpos($selector, '#') !== false)
        {
            foreach ($classes AS $index => & $class)
            {
                $class = explode('#', $class);
                if (isset($class[1]))
                {
                    $id = $class[1];
                    $class = $class[0];
                    break;
                }
            }
            unset($class);
        }
        $tag = '';
        if ($classes[0] !== '')
        {
            $tag = $classes[0];
        }
        unset($classes[0]);

        if (in_array('clone', $classes))
        {
            $this->clone = true;
        }

        if ($classes)
        {
            $len = count($classes) - 1;
            $classes = implode('|', $classes);
            $class_str = "([^'\"]*?\s)?($classes)";
            for ($i = 0; $i < $len; $i++)
            {
                $class_str .= "\s+($classes)(\s[^'\"]*?)?";
            }
            if ($class_str)
            {
                $class_regexp = "(class\s*=\s*['\"]{$class_str}(\s[^'\"]*?)?['\"])";
            }
        }

        if ($id)
        {
            $id_regexp = "(id\s*=\s*['\"]\s*$id\s*['\"])";
        }

        if (isset($class_regexp) && !$id)
        {
            $total = '.+?' . $class_regexp;
        }
        elseif (isset($id_regexp) && !isset($class_regexp))
        {
            $total = '.+?' . $id_regexp;
        }
        elseif (isset($id_regexp) && isset($class_regexp))
        {
            $total = ".+?($class_regexp|$id_regexp).+?($class_regexp|$id_regexp)";
        }
        else
        {
            $total = '';
        }

        $regexp = "/<\s*$tag$total.*?>/iu";

        foreach ($parents as & $parent)
        {
            if (preg_match_all($regexp, $parent, $match, PREG_OFFSET_CAPTURE))
            {
                $index = 0;
                foreach ($match[0] as & $m)
                {
                    if (!$tag)
                    {
                        if (preg_match("/<\s*?(\w+).+/ui", $m[0], $tag))
                        {
                            $tag = $tag[1];
                        }
                    }
                    $new_parents[] = $this->getParent($m[1], $m, $tag, $index, $parent);
                }
                unset($m);
            }
        }
        unset($parent);
        return $new_parents;
    }

    /**
     * Возвращает все элементы шаблона для вставки
     *
     * @param $selector - селектор DOM-объекта
     * @param bool|false $parents - элементы внутри которых следует искать объекты для вставки данных
     *
     * @return array|bool
     */
    private function getParents (string $selector, array $parents = null)
    {
        $selector = trim($selector);
        $layers = explode(' ', $selector);
        if (!$parents)
        {
            $parents[] = $this->template;
        }
        foreach ($layers as & $layer)
        {
            $parents = $this->getLayers($layer, $parents);
        }
        unset($layer);
        return $parents;
    }

    /**
     * Вхождение строк с искомого селектора
     *
     * @param $startpos - с какой позиции искать
     * @param array $m - массив совпадений с селектором
     * @param $tag - тег искомых элементов
     * @param $index - счетчик вложенности поиска
     * @param $parent - родительский элемент для поиска
     *
     * @return string
     */
    private function getParent (int & $startpos, array & $m, string & $tag, int & $index, string & $parent)
    {
        preg_match("/<\s*$tag.*?>/ui", $parent, $matches1, PREG_OFFSET_CAPTURE, $m[1] + strlen($m[0]));
        preg_match("/<\s*\/\s*$tag.*?>/ui", $parent, $matches2, PREG_OFFSET_CAPTURE, $m[1] + strlen($m[0]));
        if ($matches2)
        {
            if (!$matches1 || $matches1[0][1] > $matches2[0][1])
            {
                if ($index > 0)
                {
                    $index--;
                    return $this->getParent($startpos, $matches2[0], $tag, $index, $parent);
                }
                else
                {
                    $t = $matches2[0][1] + strlen($matches2[0][0]);
                    return substr($parent, $startpos, $t - $startpos);
                }
            }
            else
            {
                $start = $matches1[0][1] + strlen($matches1[0][0]);
                $end = $matches2[0][1] + strlen($matches2[0][0]) - $start;
                if (preg_match("/<\s*$tag.*?>/ui", substr($parent, $start, $end), $temp, PREG_OFFSET_CAPTURE))
                {
                    $index++;
                    return $this->getParent($startpos, $matches1[0], $tag, $index, $parent);
                }
                else
                {
                    return $this->getParent($startpos, $matches2[0], $tag, $index, $parent);
                }
            }
        }
        else
        {
            $t = $m[1] + strlen($m[0]);
            return substr($parent, $m[1], $t - $m[1]);
        }
    }

    /**
     * Вставить запась в элемент страницы
     *
     * @param array $record - запись
     * @param $parent - элемент для вставки
     *
     * @return mixed
     */
    private function insertRecord (array & $record, string $parent)
    {
        foreach ($record AS $key => & $value)
        {
            if (is_array($value))
            {
                $parents = $this->getParents('.parent.clone.' . $key, [$parent]);
                $new_parents = $this->insertData($value, $parents);
                $parent = str_replace($parents, $new_parents, $parent);
            }
            else
            {
                $parent = preg_replace("/(<[^>]+?class\s*=\s*['\"][^'\"]*?in_text_{$key}[^'\"]*?['\"].*?>)(.*?)(<\/\s*\w+?\s*>)/ui", "\${1}$value\${3}", $parent);

                $labels_exp = implode('|', $this::LABELS);
                if (preg_match_all("/<[^>]+?class\s*=\s*['\"][^'\"]*?in_($labels_exp)_{$key}(\s[^'\"]*)?['\"].*?>/ui", $parent, $match))
                {
                    foreach ($this::LABELS as $selector)
                    {
                        if (preg_match_all("/<[^>]+?class\s*=\s*['\"][^'\"]*?in_{$selector}_{$key}(\s[^'\"]*)?['\"].*?>/ui", $parent, $strings))
                        {
                            foreach ($strings[0] as & $str)
                            {
                                if (preg_match("/$selector\s*=\s*['\"].*?['\"]/iu", $str))
                                {
                                    if ($selector == 'class')
                                    {
                                        $new_str = preg_replace("/class\s*=\s*['\"](.*?)['\"]/iu", "class=\"$1 $value\"", $str);
                                    }
                                    elseif ($selector == 'href')
                                    {
                                        $new_str = preg_replace("/href\s*=\s*['\"](.*?)['\"]/iu", "href=\"\${1}$value\"", $str);
                                    }
                                    elseif ($selector == 'prop')
                                    {
                                        $new_str = preg_replace("/$selector\s*=\s*['\"].*?['\"]/iu", "$value=\"$value\"", $str);
                                    }
                                    elseif ($selector == 'src')
                                    {
                                        if ($value)
                                        {
                                            $new_str = preg_replace("/$selector\s*=\s*['\"].*?['\"]/iu", "$selector=\"$value\"", $str);
                                        }
                                        else
                                        {
                                            $new_str = $str;
                                        }
                                    }
                                    elseif ($selector == 'content')
                                    {
                                        $value = strip_tags($value);
                                        $new_str = preg_replace("/content\s*=\s*['\"](.*?)['\"]/iu", "content=\"\${1}$value\"", $str);
                                    }
                                    else
                                    {
                                        $new_str = preg_replace("/$selector\s*=\s*['\"].*?['\"]/iu", "$selector=\"$value\"", $str);
                                    }
                                }
                                else
                                {
                                    if ($selector == 'prop')
                                    {
                                        $selector = $value;
                                    }
                                    elseif ($selector == 'content')
                                    {
                                        $value = strip_tags($value);
                                    }
                                    if ($selector != 'src' || $value)
                                    {
                                        if (preg_match('/(\/>|>)/iu', $str, $m))
                                        {
                                            $new_str = preg_replace('/(>|\/>)/', '', $str) . " $selector=\"" . $value . '" ' . $m[0];
                                        }
                                        else
                                        {
                                            $new_str = $str . " $selector=\"" . $value . '" >';
                                        }
                                    }
                                }
                                if (isset($new_str))
                                {
                                    $parent = str_replace($str, $new_str, $parent);
                                    unset($new_str);
                                }
                            }
                            unset($str);
                        }
                    }
                    unset($selector);
                }
            }
        }
        unset($value);
        return $parent;
    }

    /**
     * Удалить clone-класс из определения DOM-элемента
     *
     * @param $parent - DOM-элемент
     *
     * @return mixed
     */
    private function deleteCloneClass (string $parent)
    {
        return $parent = preg_replace('/([\s\'"])(clone)([\s\'"])/iu', "$1$3", $parent, 1);
    }

    /**
     * Вставить данные в соответвтвующие элементы страницы
     *
     * @param array $data - вставляемые данные
     * @param array $parents - массив элементов страницы для вставки
     *
     * @return array
     */
    private function insertData (array & $data, array & $parents)
    {
        if (!$this->clone)
        {
            $new_data[] = isset($data[0]) ? $data[0] : $data;
            $new_parents = [];
            foreach ($parents as & $parent)
            {
                $new_parents[] = $this->insertRecord($new_data[0], $parent);
            }
            unset($parent);
        }
        else
        {
            $new_parents = $parents;
            foreach ($parents as $index => & $parent)
            {
                foreach ($data as & $record)
                {
                    if (is_array($record))
                        $new_parents[$index] .= $this->deleteCloneClass($this->insertRecord($record, $parent));
                }
                unset($record);
            }
            unset($parent);
        }
        return $new_parents;
    }

    /**
     * Вставить данные в шаблон и вернуть его
     *
     * @param array $data - массив данных
     * @param $selector - селектор элементов для вставки
     *
     * @return bool|string
     */
    public function parseResponse (array & $data, string & $selector)
    {
        $parents = $this->getParents($selector);
        $new_parents = $this->insertData($data, $parents);

        $this->template = str_replace($parents, $new_parents, $this->template);

        return $this->template;
    }
}