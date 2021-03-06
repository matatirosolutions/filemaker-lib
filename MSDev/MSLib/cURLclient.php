<?php

namespace MSDev\MSlib;

/**
 * Created:    11/09/2011
 * Project:    msLib, to be imported into where ever...
 * Created by: owenberesford aka oab1
 *
 * Class to wrap cURL, to allow software access to linked cURL client.
 * Requires cURL to be part of the PHP interpreter.
 *
 * Requires the Env class.
 *
 * Uses the following config settings:
 *  - 'curl agent' - The HTTP user-agent string - recommend 'Matatiro tester v0.0'
 *    - 'curl redirect' - Support HTTP header 302 - on or off for debug, on by default
 *  - 'network timelimit' -  Maximum time in seconds to wait for HTTP requets.
 *  - 'curl cookie jar' - Enable cookies, and say where to put the cookie cache.
 *  - 'curl verbose' - Enable the cURL verbose mode mode, so the commplete HTTP transaction is dumped
 *            to STDERR.
 *  - 'fresh cURL connection' -  Whether to preserve the cURL resource.  For sequential operations
 *            against the same host, this is a performance benefit.
 *
 *
 */

if(!function_exists('curl_init')) {
    die("Internal Error: This PHP interpreter is missing a required cURL library.");
}

class cURLclient
{
    protected $res;
    protected $env;
    protected $conf;
    protected $single;
    protected $response;

    /**
     * cURLclient constructor.
     * @param EnvironmentManager $env
     */
    function __construct(&$env)
    {
        $this->env = &$env;
        $this->res = null;
        $this->conf = $env->get('Curl');

        $this->single = $this->conf['Single'];
    }


    /**
     * @param string $url
     * @return array
     */
    public function sendGet($url)
    {
        if(!is_resource($this->res)) {
            $this->connect($url);
        }

        curl_setopt($this->res, CURLOPT_HTTPGET, 1);
        curl_setopt($this->res, CURLOPT_RETURNTRANSFER, 1);
        $this->response = curl_exec($this->res);
        $returnCode = curl_getinfo($this->res, CURLINFO_HTTP_CODE);
        if($this->single) {
            curl_close($this->res);
        }
        return array($returnCode, $this->response);
    }

    /**
     * @param string $url
     * @param string $data Use postPack or postPackFlat to convert array or object
     * @return array
     */
    public function sendPost($url, $data)
    {
        if(!is_resource($this->res)) {
            $this->connect($url);
        }

        curl_setopt($this->res, CURLOPT_POSTFIELDS, $data);
        curl_setopt($this->res, CURLOPT_POST, 1);
        curl_setopt($this->res, CURLOPT_RETURNTRANSFER, 1);
        $this->response = curl_exec($this->res);
        $returnCode = curl_getinfo($this->res, CURLINFO_HTTP_CODE);
        if($this->single) {
            curl_close($this->res);
        }
        return array($returnCode, $this->response);
    }

    /**
     * @param string $url
     * @param string $data Use postPack or postPackFlat to convert array or object
     * @return array
     */
    public function sendPut($url, $data = '')
    {
        if(!is_resource($this->res)) {
            $this->connect($url);
        }

        curl_setopt($this->res, CURLOPT_POSTFIELDS, $data);
        curl_setopt($this->res, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($this->res, CURLOPT_RETURNTRANSFER, 1);
        $this->response = curl_exec($this->res);
        $returnCode = curl_getinfo($this->res, CURLINFO_HTTP_CODE);
        if($this->single) {
            curl_close($this->res);
        }
        return array($returnCode, $this->response);
    }

    /**
     * @param $url
     */
    protected function connect($url)
    {
        $this->res = curl_init($url);

        if($this->conf['HTTPHeader']) {
            curl_setopt($this->res, CURLOPT_HTTPHEADER, $this->conf['HTTPHeader']);
        }

        curl_setopt($this->res, CURLOPT_HEADER, false);
        curl_setopt($this->res, CURLOPT_USERAGENT, $this->conf['Agent']);
        $redir = $this->conf['Redirect'];
        switch($redir) {
            case false:
                curl_setopt($this->res, CURLOPT_FOLLOWLOCATION, false);
                break;

            default:
                curl_setopt($this->res, CURLOPT_FOLLOWLOCATION, true);
                break;
        }
        curl_setopt($this->res, CURLOPT_TIMEOUT, $this->conf['Timelimit']);
        curl_setopt($this->res, CURLOPT_SSL_VERIFYPEER, $this->conf['VerifyPeer']);
        curl_setopt($this->res, CURLOPT_SSL_VERIFYHOST, $this->conf['VerifyHost']);

        if($this->conf['Username'] && $this->conf['Password']) {
            $authMethod = $this->conf['AuthMethod'] ? $this->conf['AuthMethod'] : CURLAUTH_ANY;
            curl_setopt($this->res, CURLOPT_HTTPAUTH, $authMethod);
            curl_setopt($this->res, CURLOPT_USERPWD, $this->conf['Username'] . ':' . $this->conf['Password']);
        }

        $jar = $this->conf['CookieJar'];
        if($jar) {
            curl_setopt($this->res, CURLOPT_COOKIEJAR, $jar);
        }
        if($this->conf['Verbose']) {
            curl_setopt($this->res, CURLOPT_VERBOSE, 1);
        }
    }

    /**
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param $symbol
     * @return mixed|null
     */
    public function interrogate($symbol)
    {
        if(is_resource($this->res)) {
            return curl_getinfo($this->res, $symbol);
        } else {
            return null;
        }
    }

    /**
     *
     */
    public function close()
    {
        if(is_resource($this->res)) {
            curl_close($this->res);
        }

        $this->res = null;
    }


    /**
     * Utility function to convert PHP data structures into a flat string which may be sent as a HTTP POST.
     *
     * @param array of string $args
     * @return string, the packed data.  Chars escaped to be legal over HTTP
     */
    public function postPack($args)
    {
        {
            {
                $str = '';
                foreach($args as $k => $v) {
                    if(is_array($v)) {
                        $str .= urlencode($k) . '=' . $this->postPack($v);
                    } else {
                        $str .= urlencode($k) . '=' . urlencode($v) . '&';
                    }
                }
                return $str;
            }
        }
    }

    /**
     * Utility function to pack hashes into flat strings using [] for sub-keys
     *
     * NOTE: will only correctly nest to one level deep.
     *
     * @param array $arr
     * @param bool $parent
     * @return string
     */
    public function postPackFlat($arr, $parent = false)
    {
        $str = '';
        foreach($arr as $k => $v) {
            if(is_array($v)) {
                $str .= $this->postPackFlat($v, $k);
            } else {
                if($parent) {
                    $str .= urlencode("{$parent}[{$k}]") . '=' . urlencode($v) . '&';
                } else {
                    $str .= urlencode($k) . '=' . urlencode($v) . '&';
                }
            }
        }

        return $str;
    }
}