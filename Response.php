<?php

namespace App\Libraries\ModemGSM;

class Response
{

    private $response;

    public function __construct(string $response)
    {
        $this->response = $response;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->response;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->response;
    }

    /**
     * @return bool
     */
    public function isError()
    {
        return strpos(strtoupper($this->response), 'ERROR') !== False;
    }

    /**
     * @return bool
     */
    public function isSuccess()
    {
        return strpos(strtoupper($this->response), 'ERROR') === False;
    }

    /**
     * @return bool
     */
    public function isOK()
    {
        return strpos(strtoupper($this->response), 'OK') !== False;
    }


}