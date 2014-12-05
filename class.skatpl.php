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
    private $template = false;

    private $labels = ['id', 'title', 'class', 'value', 'src', 'data-val'];

    private $clone = false;

    function __construct ($path = false)
    {
        if ($path)
        {
            if (!$this->template = file_get_contents("{$_SERVER['DOCUMENT_ROOT']}/$path"))
            {
                throw new SkaTplException("Не удалось получить шаблон $path");
            }
        }
    }

    public function setTemplate ($code)
    {
        $this->template = $code;
    }

    public function getTemplate ()
    {
        return $this->template;
    }

    private function getLayers ($selector, array $parents)
    {
        $new_parents = [];
        $classes = explode('.', $selector);
        $id = '';
        if (strpos($selector, '#') !== false)
        {
            foreach ($classes AS $index => $class)
            {
                $class = explode('#', $class);
                if (isset($class[1]))
                {
                    $id = $class[1];
                    $classes[$index] = $class[0];
                    break;
                }
            }
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

        $class_str = '';
        foreach ($classes AS $class)
        {
            $class_str .= "[^'\"]*?(" . implode('|', $classes) . ")";
        }
        if ($class_str)
        {
            $class_regexp = "(class\s*=\s*['\"]{$class_str}[^'\"]*?['\"])";
        }
        if ($id)
            $id_regexp = "(id\s*=\s*['\"]\s*$id\s*['\"])";

        if ($class_str && !$id)
        {
            $total = '.+?' . $class_regexp;
        }
        elseif ($id && !$class_str)
        {
            $total = '.+?' . $id_regexp;
        }
        elseif ($id && $class_str)
        {
            $total = ".+?($class_regexp|$id_regexp).+?($class_regexp|$id_regexp)";
        }
        else
        {
            $total = '';
        }

        $regexp = "/<\s*$tag$total.*?>/iu";

        foreach ($parents AS $parent)
        {
            if (preg_match_all($regexp, $parent, $match, PREG_OFFSET_CAPTURE))
            {
                $index = 0;
                foreach ($match[0] AS $m)
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
            }
        }
        return $new_parents;
    }

    private function getParents ($selector)
    {
        $parents = [];
        $selector = trim($selector);
        $layers = explode(' ', $selector);
        $parents[] = $this->template;
        foreach ($layers AS $layer)
        {
            $parents = $this->getLayers($layer, $parents);
        }
        return $parents;
    }

    private function getParent ($startpos, array $m, $tag, &$index, $parent)
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
                //$index++;
                $start = $matches1[0][1] + strlen($matches1[0][0]);
                $end = $matches2[0][1] + strlen($matches2[0][0]) - $start;
                //$tmp = substr($parent, $start, $end);
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

    private function insertRecord (array $record, $parent)
    {

        foreach ($record AS $key => $value)
        {
            $parent = preg_replace("/(<[^>]+?class\s*=\s*['\"][^'\"]*?in_text_{$key}[^'\"]*?['\"].*?>)(.*?)(<\/\s*\w+?\s*>)/ui", "$0$value$3", $parent);

            $labels_exp = implode('|', $this->labels);
            if (preg_match_all("/<[^>]+?class\s*=\s*['\"][^'\"]*?in_($labels_exp)_{$key}[^'\"]*?['\"].*?>/ui", $parent, $match))
            {
                foreach ($this->labels AS $selector)
                {
                    if (preg_match_all("/<[^>]+?class\s*=\s*['\"][^'\"]*?in_{$selector}_{$key}[^'\"]*?['\"].*?>/ui", $parent, $strings))
                    {
                        foreach ($strings[0] AS $str)
                        {
                            if (preg_match("/$selector\s*=\s*['\"].*?['\"]/iu", $str))
                            {
                                if ($selector !== 'class')
                                {
                                    $new_str = preg_replace("/$selector\s*=\s*['\"].*?['\"]/iu", "$selector=\"$value\"", $str);
                                }
                                else
                                {
                                    $new_str = preg_replace("/class\s*=\s*['\"](.*?)['\"]/iu", "class=\"$1 $value\"", $str);
                                }
                            }
                            else
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
                            $parent = str_replace($str, $new_str, $parent);
                        }
                    }
                }
            }
        }
        return $parent;
    }

    private function deleteCloneClass ($parent)
    {
        return preg_replace('/([\s\'"])(clone)([\s\'"])/iu', "$1$3", $parent);
    }

    private function insertData (array $data, array $parents)
    {
        if (!$this->clone)
        {
            $new_data[] = $data[0];
            $new_parents = [];
            foreach ($parents AS $parent)
            {
                $new_parents[] = $this->insertRecord($new_data[0], $this->deleteCloneClass($parent));
            }
            $this->template = str_replace($parents, $new_parents, $this->template);
        }
        else
        {
            $new_parents = $parents;
            foreach ($parents AS $index => $parent)
            {
                foreach ($data AS $record)
                {
                    $new_parents[$index] .= $this->insertRecord($record, $parent);
                }
            }
            $this->template = str_replace($parents, $new_parents, $this->template);
        }
    }

    public function parseResponse (array $data, $selector)
    {
        $parents = $this->getParents($selector);

        $this->insertData($data, $parents);

        return $this->template;
    }

}