<?php

class ApiController
{
    function index()
    {
        $options = get("/:options");

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
                $result = request($this->params['method'] ?: "GET", $url);
                $result = $result instanceof \Forward\Resource ? $result->dump(true, false) : $result;
            }
            catch(Forward\ServerException $e)
            {
                $result = array('$error' => $e->getMessage());
            }
            $end = microtime(true);
        }

        $this->index = array(
            'options' => $options,
            'result' => $result,
            'url' => $url,
            'timing' => ($end - $start)
        );
    }
}
