<?php
namespace ManaPHP\Dom;

class Selector
{
    /**
     * @var \ManaPHP\Dom\Query
     */
    protected $_query;

    /**
     * @var \DOMNode
     */
    protected $_node;

    /**
     * Selector constructor.
     *
     * @param string|\ManaPHP\Dom\Document|\DOMNode $docOrNode
     */
    public function __construct($docOrNode)
    {
        if ($docOrNode instanceof Document) {
            $this->_node = $docOrNode->getDom();
            $this->_query = new Query($this->_node);
        } elseif ($docOrNode instanceof \DOMNode) {
            $this->_node = $docOrNode;
        } else {
            $document = new Document();
            $document->load($docOrNode);
            $this->_node = $document->getDom();
            $this->_query = new Query($this->_node);
        }
    }

    /**
     * @param string|array $query
     *
     * @return \ManaPHP\Dom\SelectorList
     */
    public function xpath($query)
    {
        if (is_array($query)) {
            $tr = [];

            /** @noinspection ForeachSourceInspection */
            foreach ($query as $k => $v) {
                $tr['$' . $k] = is_int($v) ? $v : "'$v'";
            }

            $query = strtr($query[0], $tr);
        }

        $selectors = [];

        foreach ($this->_query->xpath($query, $this->_node) as $element) {
            $selector = new Selector($element);
            $selector->_query = $this->_query;
            $selectors[] = $selector;
        }
        return new SelectorList($selectors, $this);
    }

    /**
     * @param string $css
     *
     * @return \ManaPHP\Dom\SelectorList
     */
    public function css($css)
    {
        if ($css !== '' && $css[0] === '!') {
            $is_not = true;
            $css = substr($css, 1);
        } else {
            $is_not = false;
        }

        if ($pos = strpos($css, '::')) {
            $xpath = (new CssToXPath())->transform(substr($css, $pos + 2));
            $xpath = substr($css, 0, $pos + 2) . substr($xpath, 2);
        } else {
            $xpath = (new CssToXPath())->transform($css);
        }

        return $this->xpath($is_not ? "not($xpath)" : $xpath);
    }

    /**
     * @param string $css
     *
     * @return \ManaPHP\Dom\SelectorList
     */
    public function find($css = null)
    {
        return $this->css('descendant::' . ($css === null ? '*' : $css));
    }

    /**
     * @return string
     */
    public function extract()
    {
        return (string)$this->_node;
    }

    /**
     * @param string|array $attr
     * @param string       $defaultValue
     *
     * @return array|string
     */
    public function attr($attr = null, $defaultValue = null)
    {
        if ($this->_node instanceof \DOMElement) {
            $attributes = $this->_node->attributes;
        } else {
            $attributes = [];
        }

        if (is_string($attr)) {
            foreach ($attributes as $attribute) {
                if ($attribute->name === $attr) {
                    return $attribute->value;
                }
            }

            return $defaultValue;
        }

        $data = [];

        foreach ($attributes as $attribute) {
            $data[$attribute->name] = $attribute->value;
        }

        return $data;
    }

    /**
     * @return string
     */
    public function text()
    {
        return (string)$this->_node->textContent;
    }

    /**@param bool $as_string
     *
     * @return string|array
     */
    public function element($as_string = false)
    {
        if ($as_string) {
            return $this->html();
        }

        $data = [
            'name' => $this->_node->nodeName,
            'html' => $this->html(),
            'text' => $this->text(),
            'attr' => $this->attr(),
            'xpath' => $this->_node->getNodePath()
        ];

        return $data;
    }

    /**
     * @return string
     */
    public function name()
    {
        return $this->_node->nodeName;
    }

    /**
     * @return string
     */
    public function html()
    {
        /**
         * @var \DOMNode $node
         */
        $node = $this->_node;

        return $node->ownerDocument->saveHTML($node);
    }

    /**
     * @return \DOMNode
     */
    public function node()
    {
        return $this->_node;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->_node->getNodePath();
    }
}