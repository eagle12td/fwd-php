<?php

class ApiController
{
    function index()
    {
        $options = array();

        if ($this->params['url'])
        {
            $relative_url = $this->params['rel_url'];
            if ($relative_url !== "" && $relative_url[0] !== "/")
            {
                $relative_url = "/{$relative_url}";
            }
            $url = "{$this->params['url']}{$relative_url}";

            $start = microtime(true);
            try {
                if ($url === '/:options')
                {
                    $options = $this->get_options_sorted();
                    $result = $options;
                }
                else
                {
                    $result = request($this->params['method'] ?: "GET", $url);
                    $result = $result instanceof \Forward\Resource
                        ? $result->dump(true, false) : $result;
                }
            }
            catch(Forward\ServerException $e)
            {
                $result = array('$error' => $e->getMessage());
            }
            $end = microtime(true);
        }

        if (!$options) $options = $this->get_options_sorted();

        $this->index = array(
            'options' => $options,
            'result' => $result,
            'url' => $url,
            'timing' => ($end - $start)
        );
    }

    function get_options_sorted()
    {
        $options = get("/:options");
        $options = $options instanceof \Forward\Resource 
            ? $options->data() : $options;

        uksort($options, function($a, $b)
        {
            if ($a[0] == ':')
            {
                if ($b[0] == ':')
                {
                    return substr($a, 1) > substr($b, 1);
                }
                else
                {
                    return 1;
                }
            }
            else if ($b[0] == ':')
            {
                return -1;
            }
            else
            {
                return $a > $b;
            }
        });

        return $options;
    }
}
